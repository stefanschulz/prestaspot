<?php
/**
 * PrestaSpot Settings Admin Page Template
 *
 * @var array $settings
 * @package PrestaSpot
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap prestaspot-settings-wrap">
    <h1><?php esc_html_e('PrestaSpot Settings', 'prestaspot'); ?></h1>

    <p class="about-description">
        <?php esc_html_e('Connect PrestaSpot to your PrestaShop store\'s Webservice API to display products as cards via the block or shortcode.', 'prestaspot'); ?>
    </p>

    <form method="post" action="options.php">
        <?php settings_fields('prestaspot_settings_group'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="prestaspot_shop_url"><?php esc_html_e('Shop URL', 'prestaspot'); ?></label>
                </th>
                <td>
                    <input
                        type="url"
                        id="prestaspot_shop_url"
                        name="prestaspot_shop_url"
                        value="<?php echo esc_attr($settings['shop_url']); ?>"
                        class="regular-text"
                        placeholder="https://www.example-shop.com"
                    />
                    <p class="description">
                        <?php esc_html_e('Root URL of your PrestaShop store, without a trailing slash.', 'prestaspot'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="prestaspot_api_key"><?php esc_html_e('Webservice API Key', 'prestaspot'); ?></label>
                </th>
                <td>
                    <input
                        type="text"
                        id="prestaspot_api_key"
                        name="prestaspot_api_key"
                        value="<?php echo esc_attr($settings['api_key']); ?>"
                        class="regular-text"
                        autocomplete="off"
                    />
                    <p class="description">
                        <?php printf(
                            /* translators: %s: PrestaShop admin menu path */
                            esc_html__('Generated under %s in your PrestaShop back office. The key needs read (GET) access to the "products" and "images" resources.', 'prestaspot'),
                            '<code>Advanced Parameters &rarr; Webservice</code>'
                        ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="prestaspot_product_count"><?php esc_html_e('Default Product Count', 'prestaspot'); ?></label>
                </th>
                <td>
                    <input
                        type="number"
                        id="prestaspot_product_count"
                        name="prestaspot_product_count"
                        value="<?php echo esc_attr($settings['product_count']); ?>"
                        class="small-text"
                        min="1"
                        step="1"
                    />
                    <p class="description">
                        <?php esc_html_e('Used whenever the block/shortcode does not specify its own product count.', 'prestaspot'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="prestaspot_columns"><?php esc_html_e('Default Columns', 'prestaspot'); ?></label>
                </th>
                <td>
                    <input
                        type="number"
                        id="prestaspot_columns"
                        name="prestaspot_columns"
                        value="<?php echo esc_attr($settings['columns']); ?>"
                        class="small-text"
                        min="1"
                        max="6"
                        step="1"
                    />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="prestaspot_cache_duration"><?php esc_html_e('Cache Duration', 'prestaspot'); ?></label>
                </th>
                <td>
                    <input
                        type="number"
                        id="prestaspot_cache_duration"
                        name="prestaspot_cache_duration"
                        value="<?php echo esc_attr($settings['cache_duration']); ?>"
                        class="small-text"
                        min="60"
                        step="60"
                    />
                    <p class="description">
                        <?php esc_html_e('Seconds the product list is cached for before PrestaSpot queries the shop again. Minimum: 60.', 'prestaspot'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Settings', 'prestaspot')); ?>
    </form>

    <hr />

    <h2><?php esc_html_e('Usage', 'prestaspot'); ?></h2>
    <p>
        <?php esc_html_e('Add the "PrestaShop Product List" block anywhere in the block editor, or use the shortcode:', 'prestaspot'); ?>
    </p>
    <p><code>[prestaspot product_count="8" columns="4" category_id="0"]</code></p>
</div>
