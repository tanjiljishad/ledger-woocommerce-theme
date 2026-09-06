/**
 * Snapshot test for the token compiler.
 *
 * The committed generated files ARE the snapshot. This recompiles from
 * tokens.json and asserts every target still matches byte-for-byte, so an
 * accidental palette change shows up as a failing test and a readable diff.
 * Update path when a token really changes: `pnpm run tokens:build` then commit.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { relative } from 'node:path';
import { compile, readTokens, outputPaths } from '../src/compile.mjs';

const artefacts = compile(readTokens());

for (const [label, path] of Object.entries(outputPaths)) {
	test(`${label} (${relative(process.cwd(), path).replace(/\\/g, '/')}) matches committed output`, () => {
		let committed;
		try {
			committed = readFileSync(path, 'utf8');
		} catch {
			assert.fail(
				`missing generated file for ${label} — run \`pnpm run tokens:build\` and commit it`
			);
		}
		assert.equal(artefacts[path], committed);
	});
}

test('css exposes one custom property per token leaf', () => {
	const css = artefacts[outputPaths.css];
	const declarations = [...css.matchAll(/--ledger-[\w-]+:/g)];
	// 11 color + 8 space + 7 size + 3 radius + 2 shadow + 4 motion + 2 breakpoint
	assert.equal(declarations.length, 37);
});

test('theme.settings.json is valid JSON with a non-empty palette', () => {
	const doc = JSON.parse(artefacts[outputPaths.themeSettings]);
	assert.equal(doc.version, 3);
	assert.ok(Array.isArray(doc.settings.color.palette));
	assert.ok(doc.settings.color.palette.length >= 5);
	assert.equal(doc.settings.color.defaultPalette, false);
});

test('defaults.ts pins the brand color', () => {
	const ts = artefacts[outputPaths.defaults];
	assert.match(ts, /colorBrand500: "#4B3FBE"/);
	assert.match(ts, /responsiveCategories = \["space","size"\]/);
});
