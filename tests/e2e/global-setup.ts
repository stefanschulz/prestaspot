import { execSync } from 'node:child_process';

/**
 * PrestaSpot caches product/category/language/currency lookups in
 * transients (see .doc/ARCHITECTURE.md) - clear them before the run so a
 * spec never sees data left over from a previous run or from manual
 * testing against the same dev stack.
 */
export default function globalSetup(): void {
    execSync('docker exec prestaspot-docker-wordpress-1 php /repo/tests/e2e/clear-cache.php', {
        stdio: 'inherit',
    });
}
