import { test, expect, type Locator, type Page } from '@playwright/test';
import { loginAsAdmin } from './helpers/login';

/**
 * The "Custom" trigger button sits alongside the real swatches - its own
 * label text is a *sibling* span, not inside the button, so filtering by
 * visible text doesn't exclude it. Its distinguishing DOM class does.
 */
function firstThemeSwatch(palette: Locator): Locator {
    return palette.locator('button:not(.components-color-palette__custom-color-button)').first();
}

/**
 * The theme-color-palette picker (see .doc/ARCHITECTURE.md's "Theme Color
 * Palette" section) needed three iterations this session to get right -
 * a color picker that silently reverts to the default on save, or renders
 * oversized, is exactly the kind of regression that's easy to miss by eye
 * and cheap to catch here.
 */
test.describe('Settings page - button color picker', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/wp-admin/admin.php?page=prestaspot-settings');

        // Tests share persistent wp_options state across runs - start every
        // test from the known default rather than whatever a previous run
        // (or manual testing on the same dev stack) left behind.
        const resetButton = page.locator('.prestaspot-field-reset[data-target="prestaspot_button_color"]');
        if (await resetButton.isVisible()) {
            await resetButton.click();
            await page.locator('input[type="submit"]').click();
            await page.waitForLoadState('networkidle');
        }
    });

    test('picking a theme swatch persists after save and reload', async ({ page }) => {
        const input = page.locator('#prestaspot_button_color');
        const palette = page.locator('.prestaspot-color-palette').first();

        // Wait for settings-color-picker.js to mount the ColorPalette in
        // place of the plain input (see the "no-JS fallback" note in
        // ARCHITECTURE.md) before interacting with it.
        await expect(input).toHaveClass(/prestaspot-color-input--enhanced/);

        const swatch = firstThemeSwatch(palette);
        await swatch.click();
        const pickedValue = await input.inputValue();
        expect(pickedValue).toMatch(/^#[0-9a-fA-F]{6}$/);

        await page.locator('input[type="submit"]').click();
        await page.waitForLoadState('networkidle');

        await expect(page.locator('#prestaspot_button_color')).toHaveValue(pickedValue);
    });

    test('the reset button restores the default and the picker follows', async ({ page }) => {
        const input = page.locator('#prestaspot_button_color');
        const resetButton = page.locator('.prestaspot-field-reset[data-target="prestaspot_button_color"]');

        const palette = page.locator('.prestaspot-color-palette').first();
        const swatch = firstThemeSwatch(palette);
        await swatch.click();
        await expect(resetButton).toBeVisible();

        await resetButton.click();
        await expect(input).toHaveValue('#2271b1');
        await expect(resetButton).toBeHidden();
    });
});

test.describe('Settings page - image size', () => {
    async function save(page: Page) {
        await page.locator('input[type="submit"]').click();
        await page.waitForLoadState('networkidle');
    }

    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/wp-admin/admin.php?page=prestaspot-settings');
        await page.locator('#prestaspot_image_type').selectOption('medium_default');
        await page.locator('#prestaspot_image_type_custom').fill('');
        await save(page);
    });

    test('a chosen standard size persists after save and reload', async ({ page }) => {
        await page.locator('#prestaspot_image_type').selectOption('large_default');
        await save(page);

        await expect(page.locator('#prestaspot_image_type')).toHaveValue('large_default');
    });

    test('a custom image type name persists, an invalid one is dropped', async ({ page }) => {
        await page.locator('#prestaspot_image_type').selectOption('custom');
        await page.locator('#prestaspot_image_type_custom').fill('my_card_default');
        await save(page);
        await expect(page.locator('#prestaspot_image_type')).toHaveValue('custom');
        await expect(page.locator('#prestaspot_image_type_custom')).toHaveValue('my_card_default');

        // The browser's own pattern validation would block this submit, so
        // bypass it to exercise the server-side sanitizer.
        await page.evaluate(() => document.querySelector('form')!.setAttribute('novalidate', ''));
        await page.locator('#prestaspot_image_type_custom').fill('../evil');
        await save(page);
        await expect(page.locator('#prestaspot_image_type_custom')).toHaveValue('');
    });
});

test('Settings page - sort order persists after save and reload', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/wp-admin/admin.php?page=prestaspot-settings');
    await page.locator('#prestaspot_sort').selectOption('price_asc');
    await page.locator('input[type="submit"]').click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#prestaspot_sort')).toHaveValue('price_asc');

    await page.locator('#prestaspot_sort').selectOption('');
    await page.locator('input[type="submit"]').click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#prestaspot_sort')).toHaveValue('');
});
