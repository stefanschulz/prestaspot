# PrestaSpot - Changelog

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
