<?php

declare(strict_types=1);

/**
 * Exercises Presta_Spot_Api::get_categories()/get_languages() and their
 * name/code resolution helpers against the real, disposable PrestaShop
 * instance - backs both the block editor's pickers (REST routes) and the
 * shortcode's category_name/language attributes.
 */
final class CategoryAndLanguageTest extends IntegrationTestCase
{
    private Presta_Spot_Api $api;

    public function set_up(): void
    {
        parent::set_up();
        $this->api = new Presta_Spot_Api(new Presta_Spot_Settings());
    }

    public function test_categories_include_the_known_fixture_categories(): void
    {
        $names = array_column($this->api->get_categories(), 'name');

        $this->assertContains('Men', $names);
        $this->assertContains('Women', $names);
    }

    public function test_resolve_category_id_by_name_is_case_insensitive(): void
    {
        $this->assertSame(
            $this->api->resolve_category_id_by_name('men'),
            $this->api->resolve_category_id_by_name('MEN')
        );
        $this->assertGreaterThan(0, $this->api->resolve_category_id_by_name('Men'));
    }

    public function test_resolve_category_id_by_name_returns_zero_for_no_match(): void
    {
        $this->assertSame(0, $this->api->resolve_category_id_by_name('Not A Real Category'));
    }

    public function test_languages_include_english_and_german(): void
    {
        $codes = array_column($this->api->get_languages(), 'iso_code');

        $this->assertContains('en', $codes);
        $this->assertContains('de', $codes);
    }

    public function test_resolve_language_id_by_code_matches_and_falls_through(): void
    {
        $this->assertGreaterThan(0, $this->api->resolve_language_id_by_code('de'));
        $this->assertSame(0, $this->api->resolve_language_id_by_code('xx'));
    }
}
