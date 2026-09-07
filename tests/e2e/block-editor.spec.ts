import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/login';
import { deleteWpPage } from './helpers/page-fixture';

/**
 * Drives the block editor via wp.data/wp.blocks directly (page.evaluate),
 * not UI clicking - the same approach this project's own manual testing
 * settled on for Gutenberg (see .doc/DEVELOPER_GUIDE.md): far less brittle
 * than simulating the block inserter search/click flow.
 *
 * Creates its own throwaway page per test and deletes it in afterEach,
 * rather than depending on (or polluting) the persisted dev-stack fixture
 * pages documented in DEVELOPER_GUIDE.md.
 */
test.describe('Gutenberg block', () => {
    let postId: number;

    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/wp-admin/post-new.php?post_type=page');
        await page.waitForFunction(() => !!(window as any).wp?.data);

        postId = await page.evaluate(async () => {
            const wp = (window as any).wp;
            wp.data.dispatch('core/editor').editPost({ title: 'E2E Block Test', status: 'publish' });
            const block = wp.blocks.createBlock('prestaspot/product-list', {
                productCount: 5,
                categoryId: 4, // "Men" - the single-product fixture category
            });
            wp.data.dispatch('core/block-editor').resetBlocks([block]);
            await wp.data.dispatch('core/editor').savePost();
            return wp.data.select('core/editor').getCurrentPostId();
        });

        expect(postId).toBeGreaterThan(0);
    });

    test.afterEach(async ({ page }) => {
        if (postId) await deleteWpPage(page, postId);
    });

    test('category and language pickers populate from the shop, not the numeric fallback', async ({ page }) => {
        await page.goto(`/wp-admin/post.php?post=${postId}&action=edit`);
        await page.waitForFunction(() => !!(window as any).wp?.data);

        // Select the block so its Inspector sidebar renders.
        await page.evaluate(() => {
            const wp = (window as any).wp;
            const [block] = wp.data.select('core/block-editor').getBlocks();
            wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
        });

        await expect(page.getByRole('combobox', { name: 'Category' })).toBeVisible({ timeout: 10000 });
        await expect(page.getByRole('combobox', { name: 'Category' })).toContainText('Men');
        await expect(page.getByRole('combobox', { name: 'Language' })).toBeVisible();
    });

    test('the frontend renders the configured product', async ({ page }) => {
        await page.goto(`/?page_id=${postId}`);

        await expect(page.locator('.prestaspot-card')).toHaveCount(1);
        await expect(page.locator('.prestaspot-card-title')).toContainText('Hummingbird printed t-shirt');
    });
});
