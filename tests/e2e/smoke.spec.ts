import { test, expect } from '@playwright/test';

/**
 * Smoke test: the four commerce-critical routes must render without a single
 * console error or failed request. Runs on every page we ship.
 */
const routes = [
	{ name: 'shop archive', path: '/shop/' },
	{ name: 'product', path: '/shop/?orderby=price' },
	{ name: 'cart', path: '/cart/' },
	{ name: 'account', path: '/my-account/' },
];

for (const route of routes) {
	test(`${route.name} renders clean`, async ({ page }) => {
		const consoleErrors: string[] = [];
		const failedRequests: string[] = [];

		page.on('console', (msg) => {
			if (msg.type() === 'error') {
				consoleErrors.push(msg.text());
			}
		});
		page.on('requestfailed', (req) => {
			failedRequests.push(
				`${req.method()} ${req.url()} — ${req.failure()?.errorText ?? 'unknown'}`
			);
		});

		const response = await page.goto(route.path, {
			waitUntil: 'networkidle',
		});

		expect(
			response?.status(),
			`HTTP status for ${route.path}`
		).toBeLessThan(400);
		expect(consoleErrors, `console errors on ${route.path}`).toEqual([]);
		expect(failedRequests, `failed requests on ${route.path}`).toEqual([]);
	});
}
