#!/usr/bin/env node
/**
 * Competitive benchmark runner (roadmap task 1.2).
 *
 * Drives Google PageSpeed Insights (PSI) — Lighthouse mobile, run on Google's
 * infrastructure — against each competitor demo's shop archive and one product
 * page. PSI is deliberate: the exact same call is reproducible by any third
 * party (that is the point of docs/benchmarks.md as a sales-page artefact), it
 * does not compete with local work for CPU, and it clears the Cloudflare bot
 * blocks that stop a local headless browser reaching some demos.
 *
 * The API key is NEVER read from an argument or a committed file. It comes from
 * process.env.PSI_API_KEY (optionally via a gitignored .env at repo root). The
 * script exits non-zero with a clear message if it is unset.
 *
 *   PSI_API_KEY=xxxx node tools/bench/psi.mjs
 *   node tools/bench/psi.mjs --runs=3 --only=woodmart,flatsome
 *
 * Raw Lighthouse JSON for every run is written under tools/bench/results/
 * (gitignored). A distilled summary-<date>.json is written there too and a
 * Markdown table is printed to stdout for pasting into docs/benchmarks.md.
 */

import { mkdirSync, writeFileSync, readFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = join(HERE, '..', '..');
const OUT = join(HERE, 'results');

// ---------------------------------------------------------------------------
// API key: env var, or a gitignored .env at repo root. Never a CLI arg.
// ---------------------------------------------------------------------------
function loadDotEnv() {
	const p = join(REPO, '.env');
	if (!existsSync(p)) return;
	for (const line of readFileSync(p, 'utf8').split('\n')) {
		const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/i);
		if (!m) continue;
		const key = m[1];
		let val = m[2].trim();
		if (
			(val.startsWith('"') && val.endsWith('"')) ||
			(val.startsWith("'") && val.endsWith("'"))
		) {
			val = val.slice(1, -1);
		}
		if (!(key in process.env)) process.env[key] = val;
	}
}
loadDotEnv();

const KEY = process.env.PSI_API_KEY;
if (!KEY) {
	process.stderr.write(
		[
			'PSI_API_KEY is not set.',
			'',
			'Create a key in a Google Cloud project with the PageSpeed Insights API',
			'enabled, restricted to that API, then either:',
			'  - export PSI_API_KEY=xxxx   (this shell), or',
			'  - add   PSI_API_KEY=xxxx    to a .env file at the repo root (gitignored).',
			'',
			'The key must never be committed or written into docs/benchmarks.md.',
			'',
		].join('\n')
	);
	process.exit(1);
}

// ---------------------------------------------------------------------------
// CLI args
// ---------------------------------------------------------------------------
const args = Object.fromEntries(
	process.argv.slice(2).map((a) => {
		const m = a.match(/^--([^=]+)(?:=(.*))?$/);
		return m ? [m[1], m[2] ?? true] : [a, true];
	})
);
const RUNS = Number(args.runs ?? 3);
const DATE = String(args.date ?? new Date().toISOString().slice(0, 10));
const ONLY = args.only ? String(args.only).split(',').map((s) => s.trim()) : null;

// ---------------------------------------------------------------------------
// Targets. `ready:false` entries are known but not yet runnable — recorded so
// the gap is explicit in the output rather than silently missing.
//   - Kadence: no stable public demo; measured on a local standup (task 1.3).
//   - Porto / Blocksy product pages: demo blocks scraping; fill the URL when
//     known (PShop archive PSI result or a vendor doc), then flip ready:true.
// ---------------------------------------------------------------------------
const TARGETS = [
	{ theme: 'Woodmart', page: 'shop', ready: true, url: 'https://woodmart.xtemos.com/shop/' },
	{ theme: 'Woodmart', page: 'product', ready: true, url: 'https://woodmart.xtemos.com/shop/accessories/smart-watches-wood-edition/' },
	// Porto's primary /shop/ landing renders its grid client-side; the FSE
	// "Shop 1" demo serves a normal archive + product permalinks, same vendor host.
	{ theme: 'Porto', page: 'shop', ready: true, url: 'https://www.portotheme.com/wordpress/porto/shop1/shop/' },
	{ theme: 'Porto', page: 'product', ready: true, url: 'https://www.portotheme.com/wordpress/porto/shop1/product/new-balance-fresh-foam/' },
	{ theme: 'Flatsome', page: 'shop', ready: true, url: 'https://demos.flatsome.com/shop/' },
	{ theme: 'Flatsome', page: 'product', ready: true, url: 'https://demos.flatsome.com/shop/women/sweaters/on1-jersey-unif-2/' },
	{ theme: 'Blocksy', page: 'shop', ready: true, url: 'https://startersites.io/blocksy/modern-shop/shop/' },
	{ theme: 'Blocksy', page: 'product', ready: true, url: 'https://startersites.io/blocksy/modern-shop/product/aturna-condimentum-mattis-pellentesque-nibh-tortor/' },
	{ theme: 'Kadence Shop', page: 'shop', ready: false, url: null, note: 'local standup — task 1.3' },
	{ theme: 'Kadence Shop', page: 'product', ready: false, url: null, note: 'local standup — task 1.3' },
	{ theme: 'Shoptimizer', page: 'shop', ready: true, url: 'https://shoptimizerdemo.commercegurus.com/shop/' },
	{ theme: 'Shoptimizer', page: 'product', ready: true, url: 'https://shoptimizerdemo.commercegurus.com/product/endeavour-training-sports-top/' },
];

// ---------------------------------------------------------------------------
// PSI call with retry/backoff.
// ---------------------------------------------------------------------------
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function psi(url, attempt = 1) {
	const api = new URL('https://www.googleapis.com/pagespeedonline/v5/runPagespeed');
	api.searchParams.set('url', url);
	api.searchParams.set('strategy', 'mobile');
	api.searchParams.append('category', 'performance');
	api.searchParams.set('key', KEY);

	const res = await fetch(api, { signal: AbortSignal.timeout(180_000) });
	const body = await res.json().catch(() => ({}));

	if (res.ok && body.lighthouseResult) return body;

	const code = body?.error?.code ?? res.status;
	const msg = body?.error?.message ?? res.statusText;
	if ((code === 429 || code >= 500) && attempt <= 4) {
		const wait = 5_000 * attempt;
		process.stderr.write(`  ${code} ${msg} — retry ${attempt}/4 in ${wait / 1000}s\n`);
		await sleep(wait);
		return psi(url, attempt + 1);
	}
	throw new Error(`PSI ${code}: ${msg}`);
}

// ---------------------------------------------------------------------------
// Extract the metrics we report from one Lighthouse result.
// ---------------------------------------------------------------------------
function extract(body) {
	const lr = body.lighthouseResult;
	const a = lr.audits;
	const rs = Object.fromEntries(
		(a['resource-summary']?.details?.items ?? []).map((i) => [i.resourceType, i])
	);
	const net = a['network-requests']?.details?.items ?? [];
	const jqueryReq = net.find(
		(r) => /(^|\/)jquery[.\-]/i.test(r.url || '') || /\/wp-includes\/js\/jquery\//i.test(r.url || '')
	);

	return {
		lighthouseVersion: lr.lighthouseVersion,
		fetchTime: lr.fetchTime,
		finalUrl: lr.finalDisplayedUrl ?? lr.finalUrl ?? lr.requestedUrl,
		perfScore: lr.categories.performance.score,
		lcpMs: Math.round(a['largest-contentful-paint']?.numericValue ?? NaN),
		tbtMs: Math.round(a['total-blocking-time']?.numericValue ?? NaN),
		cls: Number((a['cumulative-layout-shift']?.numericValue ?? NaN).toFixed(3)),
		cssBytes: rs.stylesheet?.transferSize ?? 0,
		cssKB: Math.round((rs.stylesheet?.transferSize ?? 0) / 1024),
		jsBytes: rs.script?.transferSize ?? 0,
		jsKB: Math.round((rs.script?.transferSize ?? 0) / 1024),
		totalBytes: rs.total?.transferSize ?? 0,
		totalKB: Math.round((rs.total?.transferSize ?? 0) / 1024),
		requests: rs.total?.requestCount ?? net.length,
		jquery: jqueryReq ? 'Y' : 'N',
		jqueryUrl: jqueryReq?.url ?? null,
	};
}

const median = (nums) => {
	const s = [...nums].sort((x, y) => x - y);
	return s[Math.floor(s.length / 2)];
};

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------
mkdirSync(OUT, { recursive: true });

const chosen = TARGETS.filter(
	(t) => !ONLY || ONLY.includes(t.theme.toLowerCase().replace(/ .*/, ''))
);
const summary = [];

for (const t of chosen) {
	const id = `${t.theme.toLowerCase().replace(/\W+/g, '-')}-${t.page}`;
	if (!t.ready) {
		process.stdout.write(`SKIP  ${t.theme} / ${t.page} — ${t.note}\n`);
		summary.push({ ...t, id, status: 'skipped' });
		continue;
	}

	process.stdout.write(`\n${t.theme} / ${t.page}  ${t.url}\n`);
	const runs = [];
	for (let i = 1; i <= RUNS; i++) {
		// PSI caches ~15 min per URL, so plain repeats return one identical run.
		// A throwaway query param forces three genuinely independent Lighthouse
		// executions (WordPress ignores unknown params) — matches the
		// "?lhci=<timestamp>" cache-busting in the benchmarks.md methodology.
		const runUrl = t.url + (t.url.includes('?') ? '&' : '?') + `ledgerbench=${DATE}-${i}`;
		process.stdout.write(`  run ${i}/${RUNS} ... `);
		try {
			const body = await psi(runUrl);
			writeFileSync(join(OUT, `${id}-run${i}-${DATE}.json`), JSON.stringify(body));
			const m = extract(body);
			runs.push(m);
			process.stdout.write(
				`score ${m.perfScore} LCP ${m.lcpMs} TBT ${m.tbtMs} CLS ${m.cls} ` +
					`CSS ${m.cssKB}KB JS ${m.jsKB}KB req ${m.requests} jQuery ${m.jquery}\n`
			);
		} catch (err) {
			process.stdout.write(`FAILED ${err.message}\n`);
			runs.push({ error: err.message });
		}
		await sleep(2_000);
	}

	const ok = runs.filter((r) => !r.error);
	if (ok.length === 0) {
		summary.push({ ...t, id, status: 'failed', runs });
		continue;
	}
	// Median run = the one whose perf score is the median of the successful runs.
	const medScore = median(ok.map((r) => r.perfScore));
	const medRun =
		ok.find((r) => r.perfScore === medScore) ??
		ok.slice().sort((a, b) => a.perfScore - b.perfScore)[Math.floor(ok.length / 2)];

	summary.push({
		theme: t.theme,
		page: t.page,
		url: t.url,
		id,
		status: 'ok',
		runsCompleted: ok.length,
		lighthouseVersion: medRun.lighthouseVersion,
		testDate: DATE,
		fetchTime: medRun.fetchTime,
		median: {
			perfScore: medRun.perfScore,
			lcpMs: medRun.lcpMs,
			tbtMs: medRun.tbtMs,
			cls: medRun.cls,
			cssKB: medRun.cssKB,
			jsKB: medRun.jsKB,
			totalKB: medRun.totalKB,
			requests: medRun.requests,
			jquery: medRun.jquery,
			jqueryUrl: medRun.jqueryUrl,
		},
		spread: {
			lcpMs: ok.map((r) => r.lcpMs),
			tbtMs: ok.map((r) => r.tbtMs),
			cls: ok.map((r) => r.cls),
		},
	});
}

const summaryPath = join(OUT, `summary-${DATE}.json`);
writeFileSync(summaryPath, JSON.stringify(summary, null, 2));

// ---------------------------------------------------------------------------
// Markdown table for docs/benchmarks.md
// ---------------------------------------------------------------------------
function mdTable(page) {
	const rows = summary.filter((s) => s.page === page);
	const lh = rows.find((r) => r.status === 'ok')?.lighthouseVersion ?? '?';
	const lines = [
		`### ${page === 'shop' ? 'Shop archive' : 'Product page'}  ·  ${DATE}  ·  PSI Lighthouse ${lh}`,
		'',
		'| Theme | LCP ms | TBT ms | CLS | CSS KB | JS KB | Requests | jQuery |',
		'| --- | ---: | ---: | ---: | ---: | ---: | ---: | :---: |',
	];
	for (const r of rows) {
		if (r.status !== 'ok') {
			lines.push(`| ${r.theme} | — | — | — | — | — | — | — |  <!-- ${r.status}: ${r.note ?? ''} -->`);
			continue;
		}
		const m = r.median;
		lines.push(
			`| ${r.theme} | ${m.lcpMs} | ${m.tbtMs} | ${m.cls} | ${m.cssKB} | ${m.jsKB} | ${m.requests} | ${m.jquery} |`
		);
	}
	return lines.join('\n');
}

process.stdout.write('\n\n' + mdTable('shop') + '\n\n' + mdTable('product') + '\n');
process.stdout.write(`\nRaw JSON + ${summaryPath} written under tools/bench/results/ (gitignored).\n`);
