import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright harness. Points at the wp-env dev instance (port 8889).
 * Start it with `pnpm wp-env start` before `pnpm test:e2e`.
 */
export default defineConfig( {
	testDir: './tests/e2e',
	fullyParallel: true,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: process.env.CI ? 2 : undefined,
	reporter: process.env.CI ? [ [ 'github' ], [ 'html', { open: 'never' } ] ] : 'list',
	use: {
		baseURL: process.env.WP_BASE_URL ?? 'http://localhost:8889',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'mobile',
			use: { ...devices[ 'Pixel 5' ] },
		},
	],
} );
