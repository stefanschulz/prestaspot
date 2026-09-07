import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/login';

/**
 * Creates its own throwaway page (via the REST API, logged in as admin) and
 * deletes it afterwards - same reasoning as block-editor.spec.ts's
 * beforeEach/afterEach, kept independent of the persisted dev-stack fixture
 * pages documented in DEVELOPER_GUIDE.md so this spec doesn't depend on
 * their exact shortcode attributes staying unchanged.
 */
test.describe('Shortcode frontend rendering', () => {
    let postId: number;

    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        postId = await page.evaluate(async () => {
            const wp = (window as any).wp;
            const post = await wp.apiFetch({
                path: '/wp/v2/pages',
                method: 'POST',
                data: {
                    title: 'E2E Shortcode Test',
                    status: 'publish',
                    content: '[prestaspot category_id="4" language="de"]',
                },
            });
            return post.id;
        });

        expect(postId).toBeGreaterThan(0);
    });

    test.afterEach(async ({ page }) => {
        if (!postId) return;
        // wp.apiFetch is only loaded on wp-admin pages, not the plain
        // frontend page the test itself navigates to.
        await page.goto('/wp-admin/');
        await page.waitForFunction(() => !!(window as any).wp?.apiFetch);
        await page.evaluate(async (id) => {
            const wp = (window as any).wp;
            await wp.apiFetch({ path: `/wp/v2/pages/${id}?force=true`, method: 'DELETE' });
        }, postId);
    });

    test('language override renders the German product translation', async ({ page }) => {
        await page.goto(`/?page_id=${postId}`);

        await expect(page.locator('.prestaspot-card')).toHaveCount(1);
        await expect(page.locator('.prestaspot-card-title')).toContainText('Kolibri-bedrucktes T-Shirt');
        await expect(page.locator('.prestaspot-card-price')).toContainText('23.90');
        await expect(page.locator('.prestaspot-card-stock')).toContainText('In Stock');
    });
});
