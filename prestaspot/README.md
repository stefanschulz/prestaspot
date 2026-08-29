# PrestaSpot

Lightweight PrestaShop integration for WordPress. Displays content from a connected PrestaShop store — starting with products — as cards (image, name, description, link to the shop), via a Gutenberg block or a shortcode.

## Requirements

- WordPress 6.4+
- PHP 8.0+
- A PrestaShop store with the Webservice API enabled and an API key with read access to the `products`, `images`, `currencies`, `categories`, `configurations`, and `stock_availables` resources. Add `languages` too if your **shop** (not just your WordPress site) has more than one language configured - PrestaSpot uses that access to always scope requests to a specific shop language (which a shop with incomplete translation coverage on some products otherwise needs to avoid its product requests failing outright), to auto-detect the right one for Polylang/WPML, and to back the block/shortcode's manual language override.

## Setup

1. Activate the plugin.
2. Go to **PrestaSpot** in the WordPress admin menu.
3. Enter your shop's root URL and Webservice API key, then save.

## Usage

### Block

Add the **PrestaShop Product List** block anywhere in the block editor. Display mode, product count, columns, category filter, language override, on-sale filter, sort order, card layout, which card elements (image, name, description, price, stock status) to show, and the shop link's label/style/color are all configurable in the block sidebar. The category and language filters are proper dropdowns of your shop's actual category names/languages (fetched live from PrestaShop) rather than raw numeric ID fields - each falls back to a plain number field if that data can't be fetched (e.g. shop not yet configured, or the API key lacks `categories`/`languages` access).

### Shortcode

```
[prestaspot product_count="8" columns="4" category_id="0" category_name="" language_id="0" language="" on_sale="no" sort="" view_mode="grid" layout="image_name_description" show_image="yes" show_name="yes" show_description="yes" show_price="yes" show_stock_status="yes" price_position="after_name" link_text="Shop now" link_style="button" button_color="#2271b1" sale_badge_color="#e63946"]
```

All attributes are optional; omitted ones fall back to the defaults configured on the settings page. `category_id="0"` (the default) shows products regardless of category; `category_name="Men"` is the name-based equivalent - the shortcode has no dropdown to pick a category from the way the block does, so this looks the category up by name (case-insensitive exact match) instead of requiring a numeric ID. `category_id` wins if both are given; a `category_name` that doesn't match anything is treated the same as not specifying a category at all (no filter), not an error. `language_id`/`language` work the same way for overriding which shop language product data is fetched in - `language_id="0"` (the default) means automatic detection (see Multilingual sites below), `language="de"` is the code-based equivalent (PrestaShop's own `iso_code`, case-insensitive - not always what you'd expect, e.g. English is `gb` on some shops), `language_id` wins if both are given, and an unmatched `language` falls back to automatic detection rather than erroring. `show_image`/`show_name`/`show_description`/`show_price` accept `yes`/`no` (also `1`/`0`, `true`/`false`). `layout` is one of `image_name_description` (default), `name_image_description`, or `name_description_image` — the element render order within each card/row; the shop link always renders last, and hidden elements (via the `show_*` flags) are simply skipped. `price_position` is `after_name` (default) or `after_description` - where the price renders relative to name/description; it isn't part of `layout` itself since combining every layout with every price position would multiply the number of choices. `view_mode` is `grid` (default, `columns` applies) or `list` (stacked rows with a smaller image; `columns` is ignored). `link_text` sets the shop link's label (defaults to "View in shop"); `link_style` is `link` (default, plain text) or `button` (filled, using `button_color` as the background — text color is picked automatically for contrast).

`sort` is `""` (default - PrestaShop's own, unspecified order) or one of `name_asc`, `name_desc`, `price_asc`, `price_desc`, `date_asc` (oldest first), `date_desc` (newest first). Price sorting uses the same tax-excluded base price shown on the card; name sorting compares the product name in whichever language is currently active.

`on_sale="yes"` (default `no`) shows only products PrestaShop has flagged with its "on sale" badge - this is a flag merchants set manually per product in the back office, not an automatic detector for an active discount, so a discounted product won't show up under this filter unless that flag is also checked. The displayed price is the shop's base catalog price excluding tax (PrestaShop's tax/reduction-aware computed price isn't available for a product listing, only for a single product lookup - see `.doc/ARCHITECTURE.md` for why).

Any product PrestaShop has flagged on_sale also gets a diagonal "Sale" ribbon across its image's corner (a plain "Sale" badge instead, if the image isn't shown) - shown regardless of the `on_sale` filter above (so it's visible even when browsing an unfiltered list) and regardless of layout or which `show_*` elements are enabled. Its background color defaults to `#e63946` and is configurable via the settings page, the block's "Sale Badge Color" panel, or the `sale_badge_color` shortcode attribute - text color is picked automatically for contrast, same as the shop link button.

`show_stock_status="yes"` (default `yes`) shows an "In Stock"/"Out of Stock" label right after the price. This only appears for shops that actually have PrestaShop's stock management enabled ("Enable stock management" in **Shop Parameters → Products**) - on a shop where it's off, the label is simply never shown, rather than displaying a misleading "Out of Stock" based on stock data nobody maintains.

## Multilingual sites (Polylang or WPML)

If [Polylang](https://wordpress.org/plugins/polylang/) or [WPML](https://wpml.org/) is active, PrestaSpot automatically requests product data in the PrestaShop language matching the page's current language, so names and descriptions stay in sync with the rest of the page instead of always showing the shop's default language. This needs no configuration beyond granting the Webservice API key GET access to the `languages` resource too (see Requirements) - without it, or without either plugin, PrestaSpot falls back to its normal behavior unchanged. If both plugins are somehow active at once, Polylang takes precedence.

Without Polylang or WPML - or on a page where you deliberately want a different shop language than the page's own - the block's "Language" dropdown (or the shortcode's `language_id`/`language` attributes) lets you pick one explicitly, overriding automatic detection. `languages` API access is required for this too.

Text you type directly into a block or shortcode (e.g. a custom shop link label) is already per-language with either plugin, since each translated page is its own independent post - no plugin-specific setup needed there either. Only a *global default* set on the PrestaSpot settings page (rather than overridden per block/shortcode) is shared across all languages.

## Error handling

If any request to the PrestaShop Webservice fails (e.g., invalid URL, missing/incorrect API key, network issues), PrestaSpot stores a short‑lived transient and shows a dismissible admin notice in the WordPress dashboard (visible only to administrators). The notice disappears after dismissal or on the next successful page load.

## License

Apache License 2.0. See [LICENSE](../LICENSE).
