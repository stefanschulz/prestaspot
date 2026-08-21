<?php
/**
 * @var array $products
 * @var int $columns
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="prestaspot-grid" style="--prestaspot-columns: <?php echo esc_attr(max(1, (int)$columns)); ?>;">
    <?php if (empty($products)) : ?>
        <p class="prestaspot-empty">
            <?php esc_html_e('No products to display. Check the PrestaSpot settings (shop URL and API key).', 'prestaspot'); ?>
        </p>
    <?php else : ?>
        <?php foreach ($products as $product) : ?>
            <div class="prestaspot-card">
                <?php if (!empty($product['image_url'])) : ?>
                    <a class="prestaspot-card-image" href="<?php echo esc_url($product['permalink']); ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo esc_url($product['image_url']); ?>" alt="<?php echo esc_attr($product['name']); ?>" loading="lazy" />
                    </a>
                <?php endif; ?>
                <div class="prestaspot-card-body">
                    <h3 class="prestaspot-card-title">
                        <a href="<?php echo esc_url($product['permalink']); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($product['name']); ?>
                        </a>
                    </h3>
                    <?php if (!empty($product['description'])) : ?>
                        <p class="prestaspot-card-description"><?php echo esc_html($product['description']); ?></p>
                    <?php endif; ?>
                    <a class="prestaspot-card-link" href="<?php echo esc_url($product['permalink']); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('View in shop', 'prestaspot'); ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
