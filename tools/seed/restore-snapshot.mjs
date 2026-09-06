#!/usr/bin/env node
/**
 * Restore the committed/cached snapshot into the running wp-env instance.
 * Used by CI (`seed:restore`) instead of a live generate, and by `reset.mjs`.
 */

import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const SNAP = join(process.cwd(), 'tools', 'seed', 'snapshot');
const sql = join(SNAP, 'shop.sql');
const media = join(SNAP, 'uploads.tar.gz');

if (!existsSync(sql)) {
	process.stderr.write(
		`No snapshot at ${sql}.\nRun \`pnpm run seed:generate\` then \`pnpm run seed:export\` first.\n`
	);
	process.exit(1);
}

const run = (cmd, args) => {
	process.stdout.write(`$ ${cmd} ${args.join(' ')}\n`);
	execFileSync(cmd, args, {
		stdio: 'inherit',
		shell: process.platform === 'win32',
	});
};

const started = Date.now();

// Stage files into the mapped uploads dir so the container can read them.
run('node', [
	'-e',
	`require('fs').copyFileSync(${JSON.stringify(sql)}, '.wp-env/uploads/shop.sql')`,
]);
run('pnpm', ['wp-env', 'run', 'cli', 'wp', 'db', 'reset', '--yes']);
run('pnpm', [
	'wp-env',
	'run',
	'cli',
	'wp',
	'db',
	'import',
	'/var/www/html/wp-content/uploads/shop.sql',
]);

if (existsSync(media)) {
	run('node', [
		'-e',
		`require('fs').copyFileSync(${JSON.stringify(media)}, '.wp-env/uploads/uploads.tar.gz')`,
	]);
	run('pnpm', [
		'wp-env',
		'run',
		'cli',
		'sh',
		'-c',
		'"cd /var/www/html/wp-content/uploads && tar xzf uploads.tar.gz"',
	]);
}

run('pnpm', ['wp-env', 'run', 'cli', 'wp', 'rewrite', 'flush']);
run('pnpm', [
	'wp-env',
	'run',
	'cli',
	'wp',
	'wc',
	'tool',
	'run',
	'regenerate_thumbnails',
	'--user=admin',
]);

process.stdout.write(
	`\nRestored in ${((Date.now() - started) / 1000).toFixed(1)}s\n`
);
