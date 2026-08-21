# PrestaSpot

Lightweight PrestaShop integration for WordPress. Displays content from a connected PrestaShop store — starting with products — as cards (image, name, description, link to the shop), via a Gutenberg block or a shortcode.

## Requirements

- WordPress 6.4+
- PHP 8.0+
- A PrestaShop store with the Webservice API enabled and an API key with read access to the `products` and `images` resources (add `languages` too if your site is multilingual - see below)

## Setup

1. Activate the plugin.
2. Go to **PrestaSpot** in the WordPress admin menu.
3. Enter your shop's root URL and Webservice API key, then save.

## Usage

### Block

Add the **PrestaShop Product List** block anywhere in the block editor. Display mode, product count, columns, category filter, card layout, which card elements (image, name, description) to show, and the shop link's label/style/color are all configurable in the block sidebar.

### Shortcode

```
[prestaspot product_count="8" columns="4" category_id="0" view_mode="grid" layout="image_name_description" show_image="yes" show_name="yes" show_description="yes" link_text="Shop now" link_style="button" button_color="#2271b1"]
```

All attributes are optional; omitted ones fall back to the defaults configured on the settings page. `category_id="0"` (the default) shows products regardless of category. `show_image`/`show_name`/`show_description` accept `yes`/`no` (also `1`/`0`, `true`/`false`). `layout` is one of `image_name_description` (default), `name_image_description`, or `name_description_image` — the element render order within each card/row; the shop link always renders last, and hidden elements (via the `show_*` flags) are simply skipped. `view_mode` is `grid` (default, `columns` applies) or `list` (stacked rows with a smaller image; `columns` is ignored). `link_text` sets the shop link's label (defaults to "View in shop"); `link_style` is `link` (default, plain text) or `button` (filled, using `button_color` as the background — text color is picked automatically for contrast).

## Multilingual sites (Polylang or WPML)

If [Polylang](https://wordpress.org/plugins/polylang/) or [WPML](https://wpml.org/) is active, PrestaSpot automatically requests product data in the PrestaShop language matching the page's current language, so names and descriptions stay in sync with the rest of the page instead of always showing the shop's default language. This needs no configuration beyond granting the Webservice API key GET access to the `languages` resource too (see Requirements) - without it, or without either plugin, PrestaSpot falls back to its normal behavior unchanged. If both plugins are somehow active at once, Polylang takes precedence.

Text you type directly into a block or shortcode (e.g. a custom shop link label) is already per-language with either plugin, since each translated page is its own independent post - no plugin-specific setup needed there either. Only a *global default* set on the PrestaSpot settings page (rather than overridden per block/shortcode) is shared across all languages.

## License

Apache License 2.0. See [LICENSE](../LICENSE).
