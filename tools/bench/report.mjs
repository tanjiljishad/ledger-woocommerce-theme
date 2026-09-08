#!/usr/bin/env node
/**
 * Consolidate every raw Lighthouse JSON under tools/bench/results/ for a given
 * date into the median table for docs/benchmarks.md (roadmap 1.2).
 *
 * Reads both PSI runs (psi.mjs) and local Lighthouse runs (lh-local.mjs) — both
 * write { lighthouseResult: <lhr> }. Groups by theme + page, takes the run whose
 * performance score is the median, and prints the shop-archive and product-page
 * tables plus writes results/summary-<date>.json.
 *
 *   node tools/bench/report.mjs [--date=YYYY-MM-DD]
 */

import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const OUT = join(dirname(fileURLToPath(import.meta.url)), 'results');
const DATE =
	(process.argv.find((a) => a.startsWith('--date=')) || '').split('=')[1] ||
	new Date().toISOString().slice(0, 10);

const THEME = {
	woodmart: 'Woodmart',
	porto: 'Porto',
	flatsome: 'Flatsome',
	blocksy: 'Blocksy',
	'kadence-shop': 'Kadence Shop',
	shoptimizer: 'Shoptimizer',
};
const ORDER = ['Woodmart', 'Porto', 'Flatsome', 'Blocksy', 'Kadence Shop', 'Shoptimizer'];

function extract(body) {
	const lhr = body.lighthouseResult ?? body;
	const a = lhr.audits;
	const rs = Object.fromEntries(
		(a['resource-summary']?.details?.items ?? []).map((i) => [i.resourceType, i])
	);
	const net = a['network-requests']?.details?.items ?? [];
	const jq = net.find(
		(r) => /(^|\/)jquery[.\-]/i.test(r.url || '') || /\/wp-includes\/js\/jquery\//i.test(r.url || '')
	);
	const doc = net.find((r) => (r.resourceType || '') === 'Document');
	return {
		lighthouseVersion: lhr.lighthouseVersion,
		fetchTime: lhr.fetchTime,
		finalUrl: lhr.finalDisplayedUrl ?? lhr.requestedUrl,
		requestedUrl: lhr.requestedUrl,
		perfScore: lhr.categories.performance.score,
		lcpMs: Math.round(a['largest-contentful-paint']?.numericValue ?? NaN),
		tbtMs: Math.round(a['total-blocking-time']?.numericValue ?? NaN),
		cls: Number((a['cumulative-layout-shift']?.numericValue ?? NaN).toFixed(3)),
		cssKB: Math.round((rs.stylesheet?.transferSize ?? 0) / 1024),
		jsKB: Math.round((rs.script?.transferSize ?? 0) / 1024),
		docKB: Math.round((doc?.transferSize ?? 0) / 1024),
		totalKB: Math.round((rs.total?.transferSize ?? 0) / 1024),
		requests: rs.total?.requestCount ?? net.length,
		jquery: jq ? 'Y' : 'N',
	};
}
const median = (n) => [...n].sort((x, y) => x - y)[Math.floor(n.length / 2)];

// group files: <themeslug>-<page>[-local]-run<n>-<date>.json
const groups = {};
for (const f of readdirSync(OUT)) {
	const m = f.match(/^(.+?)-(shop|product)(?:-local)?-run\d+-(\d{4}-\d{2}-\d{2})\.json$/);
	if (!m || m[3] !== DATE) continue;
	const theme = THEME[m[1]];
	if (!theme) continue;
	const key = `${theme}|${m[2]}`;
	(groups[key] ??= { theme, page: m[2], source: f.includes('-local-') ? 'local-lighthouse' : 'psi', runs: [] });
	try {
		groups[key].runs.push({ file: f, ...extract(JSON.parse(readFileSync(join(OUT, f), 'utf8'))) });
	} catch (e) {
		groups[key].runs.push({ file: f, error: String(e.message) });
	}
}

const summary = [];
for (const g of Object.values(groups)) {
	const ok = g.runs.filter((r) => !r.error && Number.isFinite(r.lcpMs));
	if (!ok.length) {
		summary.push({ theme: g.theme, page: g.page, source: g.source, status: 'failed', runs: g.runs.length });
		continue;
	}
	const med = ok.find((r) => r.perfScore === median(ok.map((x) => x.perfScore))) ?? ok[Math.floor(ok.length / 2)];
	summary.push({
		theme: g.theme,
		page: g.page,
		source: g.source,
		status: 'ok',
		runsCompleted: ok.length,
		lighthouseVersion: med.lighthouseVersion,
		testDate: DATE,
		fetchTime: med.fetchTime,
		requestedUrl: med.requestedUrl,
		finalUrl: med.finalUrl,
		median: {
			perfScore: med.perfScore,
			lcpMs: med.lcpMs,
			tbtMs: med.tbtMs,
			cls: med.cls,
			cssKB: med.cssKB,
			jsKB: med.jsKB,
			docKB: med.docKB,
			totalKB: med.totalKB,
			requests: med.requests,
			jquery: med.jquery,
		},
		spread: { lcpMs: ok.map((r) => r.lcpMs), tbtMs: ok.map((r) => r.tbtMs), cssKB: ok.map((r) => r.cssKB), jsKB: ok.map((r) => r.jsKB) },
	});
}

writeFileSync(join(OUT, `summary-${DATE}.json`), JSON.stringify(summary, null, 2));

function table(page) {
	const rows = ORDER.map((t) => summary.find((s) => s.theme === t && s.page === page));
	const lines = [
		'| Theme | Src | Runs | LCP ms (spread) | TBT ms | CLS | CSS KB | JS KB | Requests | jQuery |',
		'| --- | :-: | :-: | --- | ---: | ---: | ---: | ---: | ---: | :-: |',
	];
	for (let i = 0; i < ORDER.length; i++) {
		const r = rows[i];
		if (!r) { lines.push(`| ${ORDER[i]} | — | — | not measured | — | — | — | — | — | — |`); continue; }
		if (r.status !== 'ok') { lines.push(`| ${r.theme} | ${r.source === 'psi' ? 'PSI' : 'LH'} | 0 | FAILED | — | — | — | — | — | — |`); continue; }
		const m = r.median;
		const sp = r.spread.lcpMs.join('/');
		lines.push(
			`| ${r.theme} | ${r.source === 'psi' ? 'PSI' : 'LH*'} | ${r.runsCompleted} | ${m.lcpMs} (${sp}) | ${m.tbtMs} | ${m.cls} | ${m.cssKB} | ${m.jsKB} | ${m.requests} | ${m.jquery} |`
		);
	}
	return lines.join('\n');
}

const lhv = summary.find((s) => s.source === 'psi' && s.status === 'ok')?.lighthouseVersion;
const lhvLocal = summary.find((s) => s.source === 'local-lighthouse' && s.status === 'ok')?.lighthouseVersion;
process.stdout.write(
	`\n# Benchmark medians — ${DATE}\n` +
		`PSI Lighthouse ${lhv ?? '?'} · local Lighthouse ${lhvLocal ?? '?'} (rows marked LH*)\n\n` +
		`## Shop archive\n${table('shop')}\n\n## Product page\n${table('product')}\n\n` +
		`summary-${DATE}.json written.\n`
);
