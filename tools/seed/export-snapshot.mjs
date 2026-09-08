#!/usr/bin/env node
/**
 * Export a restorable snapshot of the seeded shop:
 *   tools/seed/snapshot/shop.sql        (database)
 *   tools/seed/snapshot/uploads.tar.gz  (media library)
 *
 * Commit the SQL via Git LFS, or let CI cache the folder. Regenerating on every
 * CI run is far too slow (roadmap 5.3). Restore target: under 30 seconds.
 */

import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';

const SNAP = join(process.cwd(), 'tools', 'seed', 'snapshot');
mkdirSync(SNAP, { recursive: true });

const run = (cmd, args) => {
	process.stdout.write(`$ ${cmd} ${args.join(' ')}\n`);
	execFileSync(cmd, args, {
		stdio: 'inherit',
		shell: process.platform === 'win32',
	});
};
const wp = (...args) => run('pnpm', ['wp-env', 'run', 'cli', 'wp', ...args]);
const wpOut = (...args) =>
	execFileSync('pnpm', ['wp-env', 'run', 'cli', 'wp', ...args], {
		encoding: 'utf8',
		shell: process.platform === 'win32',
	}).trim();

// A snapshot must restore a *healthy* store, not one that just finished a bulk
// import. `seed generate` leaves the Action Scheduler queue full of
// per-variation attribute-lookup jobs (ADR 0015); if those ride along in the
// dump, every CI restore inherits the backlog and the slow WP-CLI bootstrap we
// spent hours diagnosing. So: rebuild the attribute-lookup table in one clean
// pass, drain what is left of the queue, then truncate the Action Scheduler
// tables before dumping.
wp('wc', 'tool', 'run', 'regenerate_product_attributes_lookup_table', '--user=admin');
for (let i = 0; i < 40; i++) {
	try {
		run('pnpm', ['wp-env', 'run', 'cli', 'wp', 'action-scheduler', 'run', '--batch-size=500']);
	} catch {
		/* a failing batch shouldn't abort the export */
	}
	const pending = Number(wpOut('action-scheduler', 'list', '--status=pending', '--format=count') || 0);
	process.stdout.write(`  action-scheduler pending: ${pending}\n`);
	if (pending <= 5) break;
}

// Assert the attribute-lookup table is fully regenerated. A silent partial
// regeneration (regen batches that never ran, or were cut off) would leave rows
// for the first N products only, and would stay invisible until Phase 3 filter
// profiling — at which point we'd be tuning the filter engine against a store
// missing half its index. Fail the export instead. (ADR 0015.)
const q = (sql) => Number(wpOut('db', 'query', sql, '--skip-column-names', '--silent') || 0);
if (!wpOut('db', 'query', "SHOW TABLES LIKE 'wp_wc_product_attributes_lookup'", '--skip-column-names', '--silent')) {
	process.stderr.write('\nEXPORT ABORTED: wp_wc_product_attributes_lookup does not exist.\n');
	process.exit(1);
}
const variableProducts = q(
	"SELECT COUNT(*) FROM wp_posts p " +
		"JOIN wp_term_relationships tr ON tr.object_id = p.ID " +
		"JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type' " +
		"JOIN wp_terms t ON t.term_id = tt.term_id AND t.slug = 'variable' " +
		"WHERE p.post_type = 'product' AND p.post_status = 'publish'"
);
const variations = q("SELECT COUNT(*) FROM wp_posts WHERE post_type = 'product_variation' AND post_status = 'publish'");
const lookupRows = q('SELECT COUNT(*) FROM wp_wc_product_attributes_lookup');
const lookupProducts = q('SELECT COUNT(DISTINCT product_id) FROM wp_wc_product_attributes_lookup');

process.stdout.write(
	`\nattribute-lookup check: ${lookupRows} rows across ${lookupProducts} products; ` +
		`store has ${variableProducts} variable products / ${variations} variations\n`
);
const problems = [];
if (lookupRows === 0) problems.push('lookup table is empty — regeneration did not run');
if (lookupProducts < variableProducts)
	problems.push(`only ${lookupProducts} of ${variableProducts} variable products are indexed — partial regeneration`);
if (lookupRows < variations)
	problems.push(`fewer lookup rows (${lookupRows}) than variations (${variations}) — every variation carries variation attributes, so this cannot be complete`);
if (problems.length) {
	process.stderr.write('\nEXPORT ABORTED — attribute-lookup table is not consistent:\n');
	for (const p of problems) process.stderr.write(`  - ${p}\n`);
	process.stderr.write('Re-run: wp wc tool run regenerate_product_attributes_lookup_table, drain Action Scheduler, then retry.\n');
	process.exit(1);
}

wp(
	'db',
	'query',
	'SET FOREIGN_KEY_CHECKS=0; ' +
		'TRUNCATE TABLE wp_actionscheduler_actions; ' +
		'TRUNCATE TABLE wp_actionscheduler_logs; ' +
		'TRUNCATE TABLE wp_actionscheduler_claims; ' +
		'SET FOREIGN_KEY_CHECKS=1;'
);

// DB dump inside the container, written to a wp-env mapped path.
run('pnpm', [
	'wp-env',
	'run',
	'cli',
	'wp',
	'db',
	'export',
	'/var/www/html/wp-content/uploads/shop.sql',
	'--add-drop-table',
]);
run('pnpm', [
	'wp-env',
	'run',
	'cli',
	'sh',
	'-c',
	`"cd /var/www/html/wp-content/uploads && tar czf uploads.tar.gz --exclude=uploads.tar.gz ."`,
]);

// wp-content/uploads is mapped to ./.wp-env/uploads in .wp-env.json.
run('node', [
	'-e',
	`require('fs').copyFileSync('.wp-env/uploads/shop.sql', ${JSON.stringify(join(SNAP, 'shop.sql'))})`,
]);
run('node', [
	'-e',
	`require('fs').copyFileSync('.wp-env/uploads/uploads.tar.gz', ${JSON.stringify(join(SNAP, 'uploads.tar.gz'))})`,
]);

process.stdout.write(`\nSnapshot written to ${SNAP}\n`);
