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

const SNAP = join( process.cwd(), 'tools', 'seed', 'snapshot' );
mkdirSync( SNAP, { recursive: true } );

const run = ( cmd, args ) => {
	process.stdout.write( `$ ${ cmd } ${ args.join( ' ' ) }\n` );
	execFileSync( cmd, args, { stdio: 'inherit', shell: process.platform === 'win32' } );
};

// DB dump inside the container, written to a wp-env mapped path.
run( 'pnpm', [ 'wp-env', 'run', 'cli', 'wp', 'db', 'export', '/var/www/html/wp-content/uploads/shop.sql', '--add-drop-table' ] );
run( 'pnpm', [ 'wp-env', 'run', 'cli', 'sh', '-c', `"cd /var/www/html/wp-content/uploads && tar czf uploads.tar.gz --exclude=uploads.tar.gz ."` ] );

// wp-content/uploads is mapped to ./.wp-env/uploads in .wp-env.json.
run( 'node', [ '-e', `require('fs').copyFileSync('.wp-env/uploads/shop.sql', ${ JSON.stringify( join( SNAP, 'shop.sql' ) ) })` ] );
run( 'node', [ '-e', `require('fs').copyFileSync('.wp-env/uploads/uploads.tar.gz', ${ JSON.stringify( join( SNAP, 'uploads.tar.gz' ) ) })` ] );

process.stdout.write( `\nSnapshot written to ${ SNAP }\n` );
