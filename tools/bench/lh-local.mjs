#!/usr/bin/env node
/**
 * Local Lighthouse runner for competitor demos that PSI cannot reach
 * (roadmap 1.2, companion to psi.mjs).
 *
 * startersites.io (the Blocksy Modern Shop demo host) serves Google's PSI
 * fetcher a Cloudflare 403 error page — PSI's numbers for it are the error
 * page, not the shop. A real headless Chromium with a normal UA passes, so
 * Blocksy is measured here instead, with Lighthouse's default mobile config
 * (Moto G-class emulation, ~Slow-4G simulated throttling, 4x CPU) — the same
 * lab config PSI wraps. This row is therefore NOT third-party reproducible the
 * way the PSI rows are; benchmarks.md marks it.
 *
 *   node tools/bench/lh-local.mjs
 *   node tools/bench/lh-local.mjs --runs=3 --chrome="C:/path/to/chrome.exe"
 */

import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = join(HERE, '..', '..');
const OUT = join(HERE, 'results');
const require = createRequire(
	pathToFileURL(join(REPO, 'node_modules/.pnpm/lighthouse@12.8.2/node_modules/lighthouse/package.json'))
);
const lighthouse = (await import(pathToFileURL(require.resolve('lighthouse')).href)).default;
const chromeLauncher = await import(pathToFileURL(require.resolve('chrome-launcher')).href);

const args = Object.fromEntries(
	process.argv.slice(2).map((a) => {
		const m = a.match(/^--([^=]+)(?:=(.*))?$/);
		return m ? [m[1], m[2] ?? true] : [a, true];
	})
);
const RUNS = Number(args.runs ?? 3);
const DATE = String(args.date ?? new Date().toISOString().slice(0, 10));
const CHROME =
	args.chrome ||
	process.env.CHROME_PATH ||
	'C:/Users/Asus/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';

const TARGETS = [
	{ theme: 'Blocksy', page: 'shop', url: 'https://startersites.io/blocksy/modern-shop/shop/' },
	{ theme: 'Blocksy', page: 'product', url: 'https://startersites.io/blocksy/modern-shop/product/aturna-condimentum-mattis-pellentesque-nibh-tortor/' },
];

function extract(lhr) {
	const a = lhr.audits;
	const rs = Object.fromEntries(
		(a['resource-summary']?.details?.items ?? []).map((i) => [i.resourceType, i])
	);
	const net = a['network-requests']?.details?.items ?? [];
	const jq = net.find(
		(r) => /(^|\/)jquery[.\-]/i.test(r.url || '') || /\/wp-includes\/js\/jquery\//i.test(r.url || '')
	);
	return {
		lighthouseVersion: lhr.lighthouseVersion,
		fetchTime: lhr.fetchTime,
		finalUrl: lhr.finalDisplayedUrl,
		perfScore: lhr.categories.performance.score,
		lcpMs: Math.round(a['largest-contentful-paint']?.numericValue ?? NaN),
		tbtMs: Math.round(a['total-blocking-time']?.numericValue ?? NaN),
		cls: Number((a['cumulative-layout-shift']?.numericValue ?? NaN).toFixed(3)),
		cssKB: Math.round((rs.stylesheet?.transferSize ?? 0) / 1024),
		jsKB: Math.round((rs.script?.transferSize ?? 0) / 1024),
		totalKB: Math.round((rs.total?.transferSize ?? 0) / 1024),
		requests: rs.total?.requestCount ?? net.length,
		jquery: jq ? 'Y' : 'N',
	};
}
const median = (n) => [...n].sort((x, y) => x - y)[Math.floor(n.length / 2)];

mkdirSync(OUT, { recursive: true });
const chrome = await chromeLauncher.launch({
	chromePath: CHROME,
	chromeFlags: ['--headless=new', '--no-sandbox', '--disable-gpu'],
});
process.stdout.write(`chrome ${CHROME} on port ${chrome.port}\n`);

const summary = [];
try {
	for (const t of TARGETS) {
		const id = `${t.theme.toLowerCase()}-${t.page}`;
		process.stdout.write(`\n${t.theme} / ${t.page}  ${t.url}\n`);
		const runs = [];
		for (let i = 1; i <= RUNS; i++) {
			const runUrl = t.url + (t.url.includes('?') ? '&' : '?') + `ledgerbench=${DATE}-${i}`;
			process.stdout.write(`  run ${i}/${RUNS} ... `);
			try {
				const { lhr } = await lighthouse(
					runUrl,
					{ port: chrome.port, output: 'json', logLevel: 'error' },
					{ extends: 'lighthouse:default', settings: { onlyCategories: ['performance'], formFactor: 'mobile' } }
				);
				writeFileSync(join(OUT, `${id}-local-run${i}-${DATE}.json`), JSON.stringify({ lighthouseResult: lhr }));
				const m = extract(lhr);
				runs.push(m);
				process.stdout.write(
					`score ${m.perfScore} LCP ${m.lcpMs} TBT ${m.tbtMs} CLS ${m.cls} CSS ${m.cssKB}KB JS ${m.jsKB}KB req ${m.requests} jQuery ${m.jquery}\n`
				);
			} catch (err) {
				process.stdout.write(`FAILED ${err.message}\n`);
				runs.push({ error: String(err.message) });
			}
		}
		const ok = runs.filter((r) => !r.error);
		if (!ok.length) {
			summary.push({ theme: t.theme, page: t.page, url: t.url, id, source: 'local-lighthouse', status: 'failed', runs });
			continue;
		}
		const medScore = median(ok.map((r) => r.perfScore));
		const medRun = ok.find((r) => r.perfScore === medScore) ?? ok[Math.floor(ok.length / 2)];
		summary.push({
			theme: t.theme,
			page: t.page,
			url: t.url,
			id,
			source: 'local-lighthouse',
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
			},
			spread: { lcpMs: ok.map((r) => r.lcpMs), tbtMs: ok.map((r) => r.tbtMs), cls: ok.map((r) => r.cls) },
		});
	}
} finally {
	await chrome.kill();
}

writeFileSync(join(OUT, `summary-local-${DATE}.json`), JSON.stringify(summary, null, 2));
process.stdout.write('\n' + JSON.stringify(summary, null, 2) + '\n');
