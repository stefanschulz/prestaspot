<?php

declare(strict_types=1);

/**
 * Exercises the image-type setting through real options: the default, a
 * stored standard choice, and the custom-name path with invalid stored data.
 */
final class ImageTypeSettingTest extends IntegrationTestCase
{
    private Presta_Spot_Settings $settings;

    public function set_up(): void
    {
        parent::set_up();
        $this->settings = new Presta_Spot_Settings();
    }

    public function test_default_is_medium_default(): void
    {
        $this->assertSame('medium_default', $this->settings->get_image_type());
    }

    public function test_stored_standard_choice_is_used(): void
    {
        update_option('prestaspot_image_type', 'large_default');

        $this->assertSame('large_default', $this->settings->get_image_type());
    }

    public function test_custom_choice_uses_the_custom_name(): void
    {
        update_option('prestaspot_image_type', 'custom');
        update_option('prestaspot_image_type_custom', 'my_card_default');

        $this->assertSame('my_card_default', $this->settings->get_image_type());
    }

    public function test_corrupt_stored_values_fall_back_to_the_default(): void
    {
        update_option('prestaspot_image_type', 'custom');
        update_option('prestaspot_image_type_custom', '../../x');

        $this->assertSame('medium_default', $this->settings->get_image_type());
    }

    public function test_settings_are_registered_with_the_sanitizers(): void
    {
        ( new Presta_Spot_Admin($this->settings) )->register_settings();
        $registered = get_registered_settings();

        $this->assertArrayHasKey('prestaspot_image_type', $registered);
        $this->assertArrayHasKey('prestaspot_image_type_custom', $registered);
        $this->assertSame('', sanitize_option('prestaspot_image_type_custom', 'bad/name'));
    }
}
