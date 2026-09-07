import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/login';

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

        // The "Custom" trigger button sits alongside the real swatches -
        // its own label text is a *sibling* span, not inside the button, so
        // filtering by visible text doesn't exclude it. Its distinguishing
        // DOM class does.
        const swatch = palette.locator('button:not(.components-color-palette__custom-color-button)').first();
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
        // The "Custom" trigger button sits alongside the real swatches -
        // its own label text is a *sibling* span, not inside the button, so
        // filtering by visible text doesn't exclude it. Its distinguishing
        // DOM class does.
        const swatch = palette.locator('button:not(.components-color-palette__custom-color-button)').first();
        await swatch.click();
        await expect(resetButton).toBeVisible();

        await resetButton.click();
        await expect(input).toHaveValue('#2271b1');
        await expect(resetButton).toBeHidden();
    });
});
