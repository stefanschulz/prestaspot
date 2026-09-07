import type { Page } from '@playwright/test';

/**
 * wp.apiFetch is only loaded on wp-admin pages, not whatever frontend page
 * a test may have ended on - navigate back before using it.
 */
export async function deleteWpPage(page: Page, id: number): Promise<void> {
    await page.goto('/wp-admin/');
    await page.waitForFunction(() => !!(window as any).wp?.apiFetch);
    await page.evaluate(async (pageId) => {
        const wp = (window as any).wp;
        await wp.apiFetch({ path: `/wp/v2/pages/${pageId}?force=true`, method: 'DELETE' });
    }, id);
}
