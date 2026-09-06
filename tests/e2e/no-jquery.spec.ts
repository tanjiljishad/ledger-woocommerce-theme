import { test, expect } from '@playwright/test';

const pages = ['/', '/shop/', '/cart/', '/my-account/'];

for (const path of pages) {
	test(`no jQuery on ${path}`, async ({ page }) => {
		await page.goto(path);
		const found = await page.evaluate(
			() =>
				typeof (window as unknown as { jQuery?: unknown }).jQuery !==
				'undefined'
		);
		expect(found, `jQuery loaded on ${path}`).toBe(false);
	});
}
