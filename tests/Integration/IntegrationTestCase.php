<?php

declare(strict_types=1);

/**
 * Shared base for the Integration suite.
 *
 * WP_UnitTestCase::set_up() unconditionally calls expectDeprecated(), whose
 * PHPUnit-11-and-up fallback path calls the removed
 * \PHPUnit\Util\Test::parseTestMethodAnnotations() - a real gap in WP core's
 * own test-case class, not something yoast/phpunit-polyfills covers. None
 * of these tests use the old @expectedDeprecated docblock annotation, so
 * overriding it to a no-op is a safe, narrowly-scoped workaround rather
 * than patching WP core files. (Same workaround DinkyChat's own test suite
 * needed - this is a WP-core/PHPUnit compatibility gap, not project-specific.)
 *
 * Likewise checkRequirements(): WP core's docblock for it merely mentions
 * "@group" in prose, which PHPUnit 11 parses as doc-comment metadata and
 * reports as a deprecation once per test class. WP itself marks the method
 * as non-functional since PHPUnit 7, so a no-op without that docblock is safe.
 */
abstract class IntegrationTestCase extends WP_UnitTestCase
{
    public function expectDeprecated(): void
    {
    }

    protected function checkRequirements(): void
    {
    }
}
