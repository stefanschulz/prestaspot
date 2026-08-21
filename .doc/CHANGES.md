# PrestaSpot - Changelog

## 0.9.0 (2026-08-21) - Price Display + On-Sale Filter

- New `show_price` boolean (global default + per-block/shortcode override, same pattern as the other card elements) renders the product price. It always sits directly after the name, regardless of `layout` - price isn't part of the layout picker's element order (that would multiply the number of layout choices), so its position is fixed rather than user-configurable.
- New `on_sale` shortcode/block parameter (instance-only, no global setting - same precedent as `category_id`) maps to `filter[on_sale]=1` on the PrestaShop products request. Note: PrestaShop's `on_sale` field is a manually-curated per-product flag ("display an on-sale badge" in the back office), not an automatic "has an active reduction" detector - a product with a live discount won't match unless that flag is also set.
- Price comes from the raw `price` field (tax-excluded) on the products list request - PrestaShop's tax/reduction-aware computed-price mechanism (`price[alias][use_tax]=1` bracket syntax) turned out, after live testing, to only work when fetching a single product by id, not on the list/collection endpoint used here, so it couldn't be used for a product listing. Displayed prices are therefore the shop's base catalog price excluding tax.
- New `Presta_Spot_Api::get_shop_currency()` (transient-cached a full day, same pattern as `get_shop_languages()`) fetches the shop's currency symbol/precision from `/api/currencies` to format the price. Since the webservice doesn't expose which currency is the shop's default, this picks the first active one - correct for the common single-currency case, best-effort otherwise. The request is scoped with `language=` (first available shop language) - without it, a multilingual shop with an untranslated currency symbol makes the webservice return an HTTP 500 on its own malformed multilingual JSON, even though the underlying data is otherwise fine.
- Requires the Webservice API key to additionally have GET access to the `currencies` resource (see `prestaspot/README.md`); missing that falls back to a bare unformatted number, same graceful-degradation approach as the language sync.

## 0.8.0 (2026-08-21) - WPML Language Sync

- Extends the language sync introduced in 0.7.0 to also support WPML, the other major WordPress multilingual plugin (Polylang is tried first, WPML second; both active at once is not a realistic scenario). `Presta_Spot_Api::get_current_polylang_iso_code()` is now paired with a `get_current_wpml_iso_code()`, both feeding a shared `get_current_language_iso_code()` used by `resolve_language_id()`.
- WPML's own language codes aren't guaranteed ISO 639-1 either (e.g. `zh-hans`, or fully custom admin-defined codes) - same fix as Polylang's non-ISO slug: resolve the current code via `apply_filters('wpml_current_language', null)`, then look up its `default_locale` via `apply_filters('wpml_active_languages', ...)` and truncate that instead of trusting the raw code.
- Verified against a minimal test double replicating WPML's documented `ICL_SITEPRESS_VERSION`/`wpml_current_language`/`wpml_active_languages` contract (no paid WPML license available in the dev environment) - confirmed correct language resolution, and confirmed Polylang takes precedence when both are active.

## 0.7.0 (2026-08-21) - Polylang Language Sync

- `Presta_Spot_Api::get_products()` now requests product data in the PrestaShop language matching the current page's Polylang language (when Polylang is active and a matching shop language is found), instead of always the shop's default language. Fully additive and optional - falls back to the unchanged prior behavior with no Polylang installed, no language match, or the API key lacking `languages` permission.
- Maps Polylang's current language to a PrestaShop `id_lang` by comparing ISO 639-1 codes: `pll_current_language('locale')` (not the default `slug` value, which is admin-editable and not guaranteed ISO-compliant) against `GET /api/languages`, cached separately from the product list for a full day (language lists change far less often than product data).
- Product transient cache key now includes the resolved language id, so different languages never share a stale cross-language cache entry.
- Verified end-to-end against a live two-language PrestaShop + Polylang setup, and confirmed graceful fallback with Polylang deactivated.
- No settings/UI changes - this is a transparent behavior improvement requiring only that the Webservice API key also has GET access to the `languages` resource (see `prestaspot/README.md`).

## 0.6.0 (2026-08-21) - Configurable Shop Link

- New `link_text` / `link_style` / `button_color` setting/attribute/shortcode-parameter trio for the shop link (previously a hardcoded "View in shop" text link). Global default + per-block/shortcode override, same pattern as every other display option.
- `link_text` empty means "use the built-in translated label" - a class constant can't hold a `__()`-translated string, so the "View in shop" fallback is applied in `templates/product-cards.php`, not `Presta_Spot_Settings`.
- `link_style` is `link` (default, unchanged appearance) or `button` (filled, background = `button_color`). New `Presta_Spot_Renderer::get_contrasting_text_color()` static method picks black/white button text for contrast - the same brightness formula DinkyChat uses for its mention-highlight colors.
- Shop Link Style gets its own visual picker (underlined-text vs. filled-swatch preview, the swatch reflecting the actually-configured `button_color`), sharing the picker shell introduced in 0.4.0/0.5.0.
- Button color itself uses `wp.blockEditor.PanelColorSettings` in the block inspector (shown only when Link Style is Button) and a native `<input type="color">` on the settings page.

## 0.5.0 (2026-08-21) - List Display Mode

- New `view_mode` setting/attribute/shortcode-parameter: `grid` (default, the existing card grid) or `list` (stacked rows with a smaller image). Global default + per-block/shortcode override, same pattern as every other display option.
- `templates/product-cards.php` now branches on `view_mode`. List mode groups consecutive non-image elements (name/description) into one stacked text column instead of every element being its own row column - so `layout=name_image_description` correctly produces a three-column row (name | image | description) rather than name and description sitting side by side. Hiding the image (or a product simply not having one) merges an otherwise-split name/description back into one contiguous text block and the row shrinks to fit, with no reserved image space left behind.
- `Presta_Spot_Settings::VIEW_MODES` + `VIEW_MODE_GRID`/`VIEW_MODE_LIST` constants, validated the same way as `layout`.
- Display Mode gets its own visual picker (grid-icon vs. list-icon cards), sharing the same picker shell as the Card Layout picker introduced in 0.4.0.

## 0.4.0 (2026-08-21) - Configurable Card Layout

- New `layout` setting/attribute/shortcode-parameter: controls the render order of image, name, and description within each card. Three variants: `image_name_description` (default), `name_image_description`, `name_description_image`. The "View in shop" link always renders last.
- Card markup flattened (`templates/product-cards.php`, `assets/css/prestaspot.css`) so the image can appear before, between, or after the text elements - previously the image lived in a fixed wrapper outside a padded "body" div holding name+description, which couldn't support the image sitting *between* them.
- Layout choice is presented as a visual picker (clickable cards with an abstract preview + radio input) rather than a plain dropdown, in both the settings page and the block inspector. New shared stylesheet: `assets/css/layout-picker.css`.

## 0.2.0 (2026-08-21) - Per-Card Element Toggles

- New `show_image` / `show_name` / `show_description` boolean settings, each with a global default (all on) and a per-block/shortcode override.
- Established the `array_key_exists()` sentinel convention for boolean overrides (vs. `!empty()` for numeric/string ones) in `Presta_Spot_Renderer::render()` - see `.doc/ARCHITECTURE.md`.

## 0.1.0 (2026-08-21) - Initial Scaffold

- Initial plugin structure, modeled on the DinkyChat plugin's conventions (autoloader, singleton bootstrap, dependency-injected classes, Settings API-based admin page).
- `Presta_Spot_Api`: PrestaShop Webservice client for products + images, transient-cached.
- `Presta_Spot_Renderer` + `templates/product-cards.php`: shared card rendering for both entry points.
- `[prestaspot]` shortcode and the dynamic `prestaspot/product-list` Gutenberg block (no build tooling - hand-written against `window.wp.*` globals), both configurable via `product_count`, `category_id`, `columns`.
- Settings page: shop URL, Webservice API key, and defaults for product count / columns / cache duration.
