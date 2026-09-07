<?php

declare(strict_types=1);

/**
 * Exercises Presta_Spot_Renderer::render()'s sentinel-resolution logic
 * (see .doc/ARCHITECTURE.md's "Two different not-specified conventions"
 * section) - the easiest kind of thing to silently regress, since a naive
 * !empty() check on a boolean option would treat an explicit `false` the
 * same as "not specified".
 */
final class RendererTest extends IntegrationTestCase
{
    private Presta_Spot_Renderer $renderer;

    public function set_up(): void
    {
        parent::set_up();
        $api = new Presta_Spot_Api(new Presta_Spot_Settings());
        $this->renderer = new Presta_Spot_Renderer(new Presta_Spot_Settings(), $api);
    }

    public function test_an_explicit_false_hides_the_element_even_though_the_default_is_true(): void
    {
        $html = $this->renderer->render(['category_id' => 4, 'show_price' => false]);

        $this->assertStringNotContainsString('prestaspot-card-price', $html);
    }

    public function test_omitting_the_key_falls_through_to_the_setting_default(): void
    {
        $html = $this->renderer->render(['category_id' => 4]);

        $this->assertStringContainsString('prestaspot-card-price', $html);
        $this->assertStringContainsString('23.90', $html);
    }

    public function test_category_name_resolves_the_same_as_category_id(): void
    {
        $by_id = $this->renderer->render(['category_id' => 4]);
        $by_name = $this->renderer->render(['category_name' => 'Men']);

        $this->assertSame($by_id, $by_name);
    }

    public function test_an_unmatched_category_name_falls_through_to_no_filter_rather_than_erroring(): void
    {
        $html = $this->renderer->render(['category_name' => 'Not A Real Category']);

        // No filter = every fixture product, not zero.
        $this->assertStringContainsString('Hummingbird printed t-shirt', $html);
    }
}
