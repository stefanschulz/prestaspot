import type { Page } from '@playwright/test';

/**
 * Dev-stack-only credentials (see .doc/DEVELOPER_GUIDE.md) - not real secrets.
 */
export async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/wp-login.php');
    await page.locator('#user_login').fill('admin');
    await page.locator('#user_pass').fill('Admin1234!secure');
    await page.locator('#wp-submit').click();
    await page.waitForURL('**/wp-admin/**', { timeout: 15000 });
}
