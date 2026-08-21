<?php
/**
 * @var array $products
 * @var int $columns
 * @var bool $show_image
 * @var bool $show_name
 * @var bool $show_description
 * @var string[] $element_order Render order of 'image'/'name'/'description'; the "View in shop" link always renders last.
 * @var string $view_mode 'grid' (card grid) or 'list' (stacked rows)
 * @var string $link_text Custom shop-link label, or '' to use the built-in translated default
 * @var string $link_style 'link' (plain text link) or 'button'
 * @var string $button_color Hex color, only used when $link_style is 'button'
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders one element's markup for one product, or '' if it has nothing to
 * show (hidden via its $show_* flag, or missing data e.g. no image/description).
 * Shared between the grid and list render paths below so the actual
 * image/name/description markup only exists once.
 */
$prestaspot_render_element = function (string $element, array $product) use ($show_image, $show_name, $show_description): string {
    if ('image' === $element) {
        if (!$show_image || empty($product['image_url'])) {
            return '';
        }
        return sprintf(
            '<a class="prestaspot-card-image" href="%1$s" target="_blank" rel="noopener noreferrer"><img src="%2$s" alt="%3$s" loading="lazy" /></a>',
            esc_url($product['permalink']),
            esc_url($product['image_url']),
            esc_attr($product['name'])
        );
    }

    if ('name' === $element) {
        if (!$show_name) {
            return '';
        }
        return sprintf(
            '<h3 class="prestaspot-card-title"><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></h3>',
            esc_url($product['permalink']),
            esc_html($product['name'])
        );
    }

    if ('description' === $element) {
        if (!$show_description || empty($product['description'])) {
            return '';
        }
        return sprintf('<p class="prestaspot-card-description">%s</p>', esc_html($product['description']));
    }

    return '';
};

$prestaspot_link_label = '' !== $link_text ? $link_text : __('View in shop', 'prestaspot');

$prestaspot_render_link = function (array $product) use ($prestaspot_link_label, $link_style, $button_color): string {
    $classes = 'prestaspot-card-link';
    $style_attr = '';

    if ('button' === $link_style) {
        $classes .= ' prestaspot-card-link--button';
        $text_color = Presta_Spot_Renderer::get_contrasting_text_color($button_color);
        $style_attr = sprintf(' style="background-color: %1$s; color: %2$s;"', esc_attr($button_color), esc_attr($text_color));
    }

    return sprintf(
        '<a class="%1$s" href="%2$s" target="_blank" rel="noopener noreferrer"%3$s>%4$s</a>',
        esc_attr($classes),
        esc_url($product['permalink']),
        $style_attr,
        esc_html($prestaspot_link_label)
    );
};

// Elements hidden via their $show_* flag are dropped up front (not just
// blanked at render time) so a hidden element can't leave behind an empty
// list-item-text wrapper, and so hiding the image correctly merges an
// otherwise-split name/description into a single contiguous text group.
$prestaspot_visible_elements = array_values(array_filter($element_order, function (string $element) use ($show_image, $show_name, $show_description): bool {
    if ('image' === $element) {
        return $show_image;
    }
    if ('name' === $element) {
        return $show_name;
    }
    if ('description' === $element) {
        return $show_description;
    }
    return false;
}));

// List mode groups consecutive non-image elements into one stacked text
// block, so e.g. name+description sit in a column next to the image instead
// of every element being its own row column. When the layout puts the image
// between name and description, that naturally produces two single-element
// text groups on either side of the image instead - still a coherent row.
$prestaspot_list_groups = array();
$prestaspot_text_buffer = array();
foreach ($prestaspot_visible_elements as $prestaspot_element) {
    if ('image' === $prestaspot_element) {
        if (!empty($prestaspot_text_buffer)) {
            $prestaspot_list_groups[] = array('type' => 'text', 'elements' => $prestaspot_text_buffer);
            $prestaspot_text_buffer = array();
        }
        $prestaspot_list_groups[] = array('type' => 'image');
    } else {
        $prestaspot_text_buffer[] = $prestaspot_element;
    }
}
if (!empty($prestaspot_text_buffer)) {
    $prestaspot_list_groups[] = array('type' => 'text', 'elements' => $prestaspot_text_buffer);
}

$prestaspot_is_list = 'list' === $view_mode;
$prestaspot_container_class = $prestaspot_is_list ? 'prestaspot-list' : 'prestaspot-grid';
$prestaspot_item_class = $prestaspot_is_list ? 'prestaspot-list-item' : 'prestaspot-card';
$prestaspot_container_style = $prestaspot_is_list ? '' : ' style="--prestaspot-columns: ' . esc_attr(max(1, (int)$columns)) . ';"';
?>
<div class="<?php echo esc_attr($prestaspot_container_class); ?>"<?php echo $prestaspot_container_style; ?>>
    <?php if (empty($products)) : ?>
        <p class="prestaspot-empty">
            <?php esc_html_e('No products to display. Check the PrestaSpot settings (shop URL and API key).', 'prestaspot'); ?>
        </p>
    <?php elseif ($prestaspot_is_list) : ?>
        <?php foreach ($products as $product) : ?>
            <div class="<?php echo esc_attr($prestaspot_item_class); ?>">
                <?php foreach ($prestaspot_list_groups as $prestaspot_group) : ?>
                    <?php if ('image' === $prestaspot_group['type']) : ?>
                        <?php echo $prestaspot_render_element('image', $product); ?>
                    <?php else : ?>
                        <div class="prestaspot-list-item-text">
                            <?php foreach ($prestaspot_group['elements'] as $prestaspot_element) : ?>
                                <?php echo $prestaspot_render_element($prestaspot_element, $product); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php echo $prestaspot_render_link($product); ?>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <?php foreach ($products as $product) : ?>
            <div class="<?php echo esc_attr($prestaspot_item_class); ?>">
                <?php foreach ($prestaspot_visible_elements as $prestaspot_element) : ?>
                    <?php echo $prestaspot_render_element($prestaspot_element, $product); ?>
                <?php endforeach; ?>
                <?php echo $prestaspot_render_link($product); ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
