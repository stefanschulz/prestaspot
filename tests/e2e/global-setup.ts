import { execSync } from 'node:child_process';

/**
 * Shells into clear-cache.php - see that file for why.
 */
export default function globalSetup(): void {
    execSync('docker exec prestaspot-docker-wordpress-1 php /repo/tests/e2e/clear-cache.php', {
        stdio: 'inherit',
    });
}
