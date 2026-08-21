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

// Renders a small icon button next to a field's label; JS (settings-page.js)
// shows it only once that field's live value has drifted from $default_value,
// and resets the field to it on click - see class-presta-spot-admin.php's
// enqueue_admin_scripts() for where that script is loaded.
$prestaspot_reset_button = function (string $target_name, string $default_value): string {
    $label = esc_attr__('Reset to default', 'prestaspot');
    return sprintf(
        '<button type="button" class="prestaspot-field-reset" data-target="%1$s" data-default="%2$s" aria-label="%3$s" title="%3$s"><span class="dashicons dashicons-undo" aria-hidden="true"></span></button>',
        esc_attr($target_name),
        esc_attr($default_value),
        $label
    );
};
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
                    <?php echo $prestaspot_reset_button('prestaspot_product_count', (string)Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::PRODUCT_COUNT]); ?>
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
                    <?php echo $prestaspot_reset_button('prestaspot_columns', (string)Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::COLUMNS]); ?>
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
                    <?php echo $prestaspot_reset_button('prestaspot_cache_duration', (string)Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::CACHE_DURATION]); ?>
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
                <th scope="row">
                    <label for="prestaspot_sort"><?php esc_html_e('Sort By', 'prestaspot'); ?></label>
                    <?php echo $prestaspot_reset_button('prestaspot_sort', Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::SORT]); ?>
                </th>
                <td>
                    <?php
                    $prestaspot_sort_options = array(
                        Presta_Spot_Settings::SORT_DEFAULT => __('Default', 'prestaspot'),
                        Presta_Spot_Settings::SORT_NAME_ASC => __('Name (A-Z)', 'prestaspot'),
                        Presta_Spot_Settings::SORT_NAME_DESC => __('Name (Z-A)', 'prestaspot'),
                        Presta_Spot_Settings::SORT_PRICE_ASC => __('Price (Low to High)', 'prestaspot'),
                        Presta_Spot_Settings::SORT_PRICE_DESC => __('Price (High to Low)', 'prestaspot'),
                        Presta_Spot_Settings::SORT_DATE_DESC => __('Newest First', 'prestaspot'),
                        Presta_Spot_Settings::SORT_DATE_ASC => __('Oldest First', 'prestaspot'),
                    );
                    ?>
                    <select id="prestaspot_sort" name="prestaspot_sort">
                        <?php foreach ($prestaspot_sort_options as $prestaspot_sort_value => $prestaspot_sort_label) : ?>
                            <option value="<?php echo esc_attr($prestaspot_sort_value); ?>" <?php selected($settings['sort'], $prestaspot_sort_value); ?>>
                                <?php echo esc_html($prestaspot_sort_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('"Default" leaves the order up to PrestaShop (unspecified).', 'prestaspot'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php esc_html_e('Display Mode', 'prestaspot'); ?>
                    <?php echo $prestaspot_reset_button('prestaspot_view_mode', Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::VIEW_MODE]); ?>
                </th>
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
                <th scope="row">
                    <?php esc_html_e('Card Layout', 'prestaspot'); ?>
                    <?php echo $prestaspot_reset_button('prestaspot_layout', Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::LAYOUT]); ?>
                </th>
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
                        <?php echo $prestaspot_reset_button('prestaspot_show_image', Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::SHOW_IMAGE] ? '1' : '0'); ?>
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
                        <?php echo $prestaspot_reset_button('prestaspot_show_name', Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::SHOW_NAME] ? '1' : '0'); ?>
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
                        <?php echo $prestaspot_reset_button('prestaspot_show_description', Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::SHOW_DESCRIPTION] ? '1' : '0'); ?>
                    </p>
                    <p>
                        <label for="prestaspot_show_price">
                            <input type="hidden" name="prestaspot_show_price" value="0" />
                            <input
                                type="checkbox"
                                id="prestaspot_show_price"
                                name="prestaspot_show_price"
                                value="1"
                                <?php checked($settings['show_price']); ?>
                            />
                            <?php esc_html_e('Show price', 'prestaspot'); ?>
                        </label>
                        <?php echo $prestaspot_reset_button('prestaspot_show_price', Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::SHOW_PRICE] ? '1' : '0'); ?>
                    </p>
                    <p class="description">
                        <?php esc_html_e('Default visibility of product card elements. Can be overridden per block or shortcode.', 'prestaspot'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php esc_html_e('Price Position', 'prestaspot'); ?>
                    <?php echo $prestaspot_reset_button('prestaspot_price_position', Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::PRICE_POSITION]); ?>
                </th>
                <td>
                    <?php
                    $prestaspot_price_position_options = array(
                        Presta_Spot_Settings::PRICE_POSITION_AFTER_NAME => __('After Name', 'prestaspot'),
                        Presta_Spot_Settings::PRICE_POSITION_AFTER_DESCRIPTION => __('After Description', 'prestaspot'),
                    );
                    $prestaspot_price_position_previews = array(
                        Presta_Spot_Settings::PRICE_POSITION_AFTER_NAME => array('name', 'price', 'description'),
                        Presta_Spot_Settings::PRICE_POSITION_AFTER_DESCRIPTION => array('name', 'description', 'price'),
                    );
                    ?>
                    <div class="prestaspot-layout-picker">
                        <?php foreach ($prestaspot_price_position_options as $prestaspot_price_position_value => $prestaspot_price_position_label) : ?>
                            <label class="prestaspot-layout-option">
                                <input
                                    type="radio"
                                    name="prestaspot_price_position"
                                    value="<?php echo esc_attr($prestaspot_price_position_value); ?>"
                                    <?php checked($settings['price_position'], $prestaspot_price_position_value); ?>
                                />
                                <span class="prestaspot-layout-preview">
                                    <?php foreach ($prestaspot_price_position_previews[$prestaspot_price_position_value] as $prestaspot_element) : ?>
                                        <span class="prestaspot-layout-block prestaspot-layout-block--<?php echo esc_attr($prestaspot_element); ?>"></span>
                                    <?php endforeach; ?>
                                </span>
                                <span class="prestaspot-layout-label"><?php echo esc_html($prestaspot_price_position_label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Where the price renders relative to name/description. Only used when "Show price" is enabled.', 'prestaspot'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="prestaspot_link_text"><?php esc_html_e('Shop Link Text', 'prestaspot'); ?></label>
                    <?php echo $prestaspot_reset_button('prestaspot_link_text', Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::LINK_TEXT]); ?>
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
                <th scope="row">
                    <?php esc_html_e('Shop Link Style', 'prestaspot'); ?>
                    <?php echo $prestaspot_reset_button('prestaspot_link_style', Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::LINK_STYLE]); ?>
                </th>
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
                    <?php echo $prestaspot_reset_button('prestaspot_button_color', Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::BUTTON_COLOR]); ?>
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
            <tr>
                <th scope="row">
                    <label for="prestaspot_sale_badge_color"><?php esc_html_e('Sale Badge Color', 'prestaspot'); ?></label>
                    <?php echo $prestaspot_reset_button('prestaspot_sale_badge_color', Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::SALE_BADGE_COLOR]); ?>
                </th>
                <td>
                    <input
                        type="color"
                        id="prestaspot_sale_badge_color"
                        name="prestaspot_sale_badge_color"
                        value="<?php echo esc_attr($settings['sale_badge_color']); ?>"
                    />
                    <p class="description">
                        <?php esc_html_e('Background of the "Sale" ribbon/badge shown on products flagged on sale. Text color is picked automatically for contrast.', 'prestaspot'); ?>
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
    <p><code>[prestaspot product_count="8" columns="4" category_id="0" on_sale="no" sort="" view_mode="grid" layout="image_name_description" show_image="yes" show_name="yes" show_description="yes" show_price="yes" price_position="after_name" link_text="Shop now" link_style="button" button_color="#2271b1" sale_badge_color="#e63946"]</code></p>
</div>
