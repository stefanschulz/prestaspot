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
                    <p class="description">
                        <?php esc_html_e('Only used in grid display mode.', 'prestaspot'); ?>
                    </p>
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
            <tr>
                <th scope="row"><?php esc_html_e('Display Mode', 'prestaspot'); ?></th>
                <td>
                    <?php
                    $prestaspot_view_mode_options = array(
                        Presta_Spot_Settings::VIEW_MODE_GRID => __('Grid', 'prestaspot'),
                        Presta_Spot_Settings::VIEW_MODE_LIST => __('List', 'prestaspot'),
                    );
                    ?>
                    <div class="prestaspot-layout-picker">
                        <?php foreach ($prestaspot_view_mode_options as $prestaspot_view_mode_value => $prestaspot_view_mode_label) : ?>
                            <label class="prestaspot-layout-option">
                                <input
                                    type="radio"
                                    name="prestaspot_view_mode"
                                    value="<?php echo esc_attr($prestaspot_view_mode_value); ?>"
                                    <?php checked($settings['view_mode'], $prestaspot_view_mode_value); ?>
                                />
                                <span class="prestaspot-viewmode-preview prestaspot-viewmode-preview--<?php echo esc_attr($prestaspot_view_mode_value); ?>">
                                    <?php if (Presta_Spot_Settings::VIEW_MODE_GRID === $prestaspot_view_mode_value) : ?>
                                        <span></span><span></span><span></span><span></span>
                                    <?php else : ?>
                                        <span></span><span></span><span></span>
                                    <?php endif; ?>
                                </span>
                                <span class="prestaspot-layout-label"><?php echo esc_html($prestaspot_view_mode_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Grid shows products as cards; list stacks them as rows with a smaller image.', 'prestaspot'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Card Layout', 'prestaspot'); ?></th>
                <td>
                    <?php
                    $prestaspot_layout_labels = array(
                        Presta_Spot_Settings::LAYOUT_IMAGE_NAME_DESCRIPTION => __('Image, Name, Description', 'prestaspot'),
                        Presta_Spot_Settings::LAYOUT_NAME_IMAGE_DESCRIPTION => __('Name, Image, Description', 'prestaspot'),
                        Presta_Spot_Settings::LAYOUT_NAME_DESCRIPTION_IMAGE => __('Name, Description, Image', 'prestaspot'),
                    );
                    ?>
                    <div class="prestaspot-layout-picker">
                        <?php foreach (Presta_Spot_Settings::LAYOUT_ELEMENT_ORDER as $prestaspot_layout_value => $prestaspot_element_order) : ?>
                            <label class="prestaspot-layout-option">
                                <input
                                    type="radio"
                                    name="prestaspot_layout"
                                    value="<?php echo esc_attr($prestaspot_layout_value); ?>"
                                    <?php checked($settings['layout'], $prestaspot_layout_value); ?>
                                />
                                <span class="prestaspot-layout-preview">
                                    <?php foreach ($prestaspot_element_order as $prestaspot_element) : ?>
                                        <span class="prestaspot-layout-block prestaspot-layout-block--<?php echo esc_attr($prestaspot_element); ?>"></span>
                                    <?php endforeach; ?>
                                </span>
                                <span class="prestaspot-layout-label"><?php echo esc_html($prestaspot_layout_labels[$prestaspot_layout_value]); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Order the visible card elements render in. The "View in shop" link always renders last.', 'prestaspot'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Card Elements', 'prestaspot'); ?></th>
                <td>
                    <p>
                        <label for="prestaspot_show_image">
                            <input type="hidden" name="prestaspot_show_image" value="0" />
                            <input
                                type="checkbox"
                                id="prestaspot_show_image"
                                name="prestaspot_show_image"
                                value="1"
                                <?php checked($settings['show_image']); ?>
                            />
                            <?php esc_html_e('Show image', 'prestaspot'); ?>
                        </label>
                    </p>
                    <p>
                        <label for="prestaspot_show_name">
                            <input type="hidden" name="prestaspot_show_name" value="0" />
                            <input
                                type="checkbox"
                                id="prestaspot_show_name"
                                name="prestaspot_show_name"
                                value="1"
                                <?php checked($settings['show_name']); ?>
                            />
                            <?php esc_html_e('Show name', 'prestaspot'); ?>
                        </label>
                    </p>
                    <p>
                        <label for="prestaspot_show_description">
                            <input type="hidden" name="prestaspot_show_description" value="0" />
                            <input
                                type="checkbox"
                                id="prestaspot_show_description"
                                name="prestaspot_show_description"
                                value="1"
                                <?php checked($settings['show_description']); ?>
                            />
                            <?php esc_html_e('Show description', 'prestaspot'); ?>
                        </label>
                    </p>
                    <p class="description">
                        <?php esc_html_e('Default visibility of product card elements. Can be overridden per block or shortcode.', 'prestaspot'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="prestaspot_link_text"><?php esc_html_e('Shop Link Text', 'prestaspot'); ?></label>
                </th>
                <td>
                    <input
                        type="text"
                        id="prestaspot_link_text"
                        name="prestaspot_link_text"
                        value="<?php echo esc_attr($settings['link_text']); ?>"
                        class="regular-text"
                        placeholder="<?php echo esc_attr__('View in shop', 'prestaspot'); ?>"
                    />
                    <p class="description">
                        <?php esc_html_e('Leave empty to use the default label shown above as a placeholder.', 'prestaspot'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Shop Link Style', 'prestaspot'); ?></th>
                <td>
                    <?php
                    $prestaspot_link_style_options = array(
                        Presta_Spot_Settings::LINK_STYLE_LINK => __('Link', 'prestaspot'),
                        Presta_Spot_Settings::LINK_STYLE_BUTTON => __('Button', 'prestaspot'),
                    );
                    ?>
                    <div class="prestaspot-layout-picker">
                        <?php foreach ($prestaspot_link_style_options as $prestaspot_link_style_value => $prestaspot_link_style_label) : ?>
                            <label class="prestaspot-layout-option">
                                <input
                                    type="radio"
                                    name="prestaspot_link_style"
                                    value="<?php echo esc_attr($prestaspot_link_style_value); ?>"
                                    <?php checked($settings['link_style'], $prestaspot_link_style_value); ?>
                                />
                                <span class="prestaspot-linkstyle-preview prestaspot-linkstyle-preview--<?php echo esc_attr($prestaspot_link_style_value); ?>">
                                    <?php if (Presta_Spot_Settings::LINK_STYLE_BUTTON === $prestaspot_link_style_value) : ?>
                                        <span style="background-color: <?php echo esc_attr($settings['button_color']); ?>;"></span>
                                    <?php else : ?>
                                        <span></span>
                                    <?php endif; ?>
                                </span>
                                <span class="prestaspot-layout-label"><?php echo esc_html($prestaspot_link_style_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="prestaspot_button_color"><?php esc_html_e('Shop Link Button Color', 'prestaspot'); ?></label>
                </th>
                <td>
                    <input
                        type="color"
                        id="prestaspot_button_color"
                        name="prestaspot_button_color"
                        value="<?php echo esc_attr($settings['button_color']); ?>"
                    />
                    <p class="description">
                        <?php esc_html_e('Only used when Shop Link Style is Button. Text color is picked automatically for contrast.', 'prestaspot'); ?>
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
    <p><code>[prestaspot product_count="8" columns="4" category_id="0" view_mode="grid" layout="image_name_description" show_image="yes" show_name="yes" show_description="yes" link_text="Shop now" link_style="button" button_color="#2271b1"]</code></p>
</div>
