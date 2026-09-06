#!/usr/bin/env node
/**
 * One command: wipe local, restore the snapshot, leave a working shop.
 * You will run this hundreds of times (roadmap 5.4).
 *
 *   pnpm run reset
 */

import { execFileSync } from 'node:child_process';

const run = (cmd, args) => {
	process.stdout.write(`\n$ ${cmd} ${args.join(' ')}\n`);
	execFileSync(cmd, args, {
		stdio: 'inherit',
		shell: process.platform === 'win32',
	});
};

run('pnpm', ['wp-env', 'start']);
run('node', ['tools/seed/restore-snapshot.mjs']);
run('pnpm', ['wp-env', 'run', 'cli', 'wp', 'cache', 'flush']);
run('pnpm', ['wp-env', 'run', 'cli', 'wp', 'transient', 'delete', '--all']);

process.stdout.write('\nShop reset. http://localhost:8889/shop/\n');
