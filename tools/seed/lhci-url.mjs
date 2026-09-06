#!/usr/bin/env node
/**
 * Print the first published product's permalink and post ID from the running
 * wp-env instance, and rewrite SEED_ID in lighthouserc.js so the product-page
 * assertion targets a real page. Run after seed:restore, before lhci.
 */

import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';

const out = execFileSync(
	'pnpm',
	[ 'wp-env', 'run', 'cli', 'wp', 'post', 'list', '--post_type=product', '--post_status=publish', '--posts_per_page=1', '--field=ID' ],
	{ encoding: 'utf8', shell: process.platform === 'win32' }
);

const id = out.trim().split( /\s+/ ).pop();
if ( ! /^\d+$/.test( id ?? '' ) ) {
	process.stderr.write( `Could not read a product ID (got: ${ JSON.stringify( out ) })\n` );
	process.exit( 1 );
}

const rc = 'lighthouserc.js';
const patched = readFileSync( rc, 'utf8' ).replace( /(\bp=)SEED_ID\b/, `$1${ id }` );
writeFileSync( rc, patched );
process.stdout.write( `lighthouserc.js -> product p=${ id }\n` );
