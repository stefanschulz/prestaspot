# PrestaSpot

Lightweight PrestaShop integration for WordPress. Displays content from a connected PrestaShop store — starting with products — as cards (image, name, description, link to the shop), via a Gutenberg block or a shortcode.

## Requirements

- WordPress 6.4+
- PHP 8.0+
- A PrestaShop store with the Webservice API enabled and an API key with read access to the `products` and `images` resources

## Setup

1. Activate the plugin.
2. Go to **PrestaSpot** in the WordPress admin menu.
3. Enter your shop's root URL and Webservice API key, then save.

## Usage

### Block

Add the **PrestaShop Product List** block anywhere in the block editor. Product count, columns, category filter, and which card elements (image, name, description) to show are configurable in the block sidebar.

### Shortcode

```
[prestaspot product_count="8" columns="4" category_id="0" show_image="yes" show_name="yes" show_description="yes"]
```

All attributes are optional; omitted ones fall back to the defaults configured on the settings page. `category_id="0"` (the default) shows products regardless of category. `show_image`/`show_name`/`show_description` accept `yes`/`no` (also `1`/`0`, `true`/`false`).

## License

Apache License 2.0. See [LICENSE](../LICENSE).
