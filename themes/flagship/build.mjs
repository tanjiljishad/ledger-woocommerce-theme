#!/usr/bin/env node
/**
 * Theme build. No bundler in Phase 0 — this only:
 *   1. copies packages/tokens/dist/tokens.css -> themes/flagship/assets/tokens.css
 *   2. copies src/style.css -> build/style.css (whitespace-collapsed)
 *
 * Swap in a real pipeline (Lightning CSS / esbuild) when the theme grows past
 * a single stylesheet. The size budget in .size-limit.json applies either way.
 */

import { mkdirSync, readFileSync, writeFileSync, copyFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname( fileURLToPath( import.meta.url ) );
const REPO = join( HERE, '..', '..' );

mkdirSync( join( HERE, 'assets' ), { recursive: true } );
mkdirSync( join( HERE, 'build' ), { recursive: true } );

copyFileSync(
	join( REPO, 'packages', 'tokens', 'dist', 'tokens.css' ),
	join( HERE, 'assets', 'tokens.css' )
);

const src = readFileSync( join( HERE, 'src', 'style.css' ), 'utf8' );
const min = src
	.replace( /\/\*[\s\S]*?\*\//g, '' )
	.replace( /\s+/g, ' ' )
	.replace( /\s*([{}:;,>])\s*/g, '$1' )
	.replace( /;}/g, '}' )
	.trim();

writeFileSync( join( HERE, 'build', 'style.css' ), min + '\n' );

process.stdout.write( '  theme: wrote assets/tokens.css and build/style.css\n' );
