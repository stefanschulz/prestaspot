# PrestaSpot - Technical Architecture

Technical reference for the PrestaSpot plugin's structure and class contracts, for developers and LLMs working on the codebase.

**Version**: 0.4.0

---

## File Structure

```
prestaspot/
├── prestaspot.php                         # Plugin header, constants, autoloader, singleton bootstrap
├── includes/
│   ├── class-presta-spot-settings.php       # Settings model: option keys, defaults, sanitized get_all()/get()
│   ├── class-presta-spot-api.php            # PrestaShop Webservice client (products + images), transient-cached
│   ├── class-presta-spot-renderer.php       # Merges instance args with settings, renders templates/product-cards.php
│   ├── class-presta-spot-shortcode.php      # Registers [prestaspot], maps shortcode_atts() to renderer args
│   ├── class-presta-spot-block.php          # Registers the dynamic prestaspot/product-list block, maps block attributes to renderer args
│   ├── class-presta-spot-admin.php          # Settings page (wp-admin), Settings API registration, admin asset enqueue
│   └── class-presta-spot-frontend.php       # Enqueues assets/css/prestaspot.css on the frontend
├── templates/
│   ├── product-cards.php                    # Card grid markup, shared by shortcode and block
│   └── settings-page.php                    # Admin settings page markup
├── blocks/product-list/
│   ├── block.json                           # Block metadata + attributes (apiVersion 3)
│   ├── index.js                             # Editor script - InspectorControls, ServerSideRender preview
│   └── index.asset.php                      # Manually declares editor script dependencies (see "No build tooling" below)
├── assets/css/
│   ├── prestaspot.css                       # Card grid + card styles (frontend, and block "style")
│   └── layout-picker.css                    # Visual layout picker (shared by settings page and block editor)
├── LICENSE                                  # Apache License 2.0
└── build.xml / build-package.php            # Phing target that zips prestaspot/ into prestaspot-<version>.zip (repo root, not inside the plugin folder)
```

---

## Core Constants

Defined in `prestaspot.php`, same pattern as the reference project this plugin followed (DinkyChat):

```php
define('PRESTASPOT_VERSION', prestaspot_get_version()); // Parsed from the "Version:" line in this file's own header
define('PRESTASPOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRESTASPOT_PLUGIN_URL', plugin_dir_url(__FILE__));
```

**Asset versioning**: `wp_enqueue_style()` calls use `prestaspot_get_asset_version($relative_path)`, not `PRESTASPOT_VERSION` - it returns the asset file's own `filemtime()`, falling back to `PRESTASPOT_VERSION` only if the file is missing. An edited CSS file is fetched fresh immediately without bumping the plugin version.

**Autoloader**: `Presta_Spot_Foo_Bar` → `includes/class-presta-spot-foo-bar.php` (same `str_replace('_', '-', strtolower(...))` convention as DinkyChat).

---

## Bootstrap (`Presta_Spot_Plugin`)

Singleton, constructed eagerly (not deferred to a hook) since `Presta_Spot_Admin::init()` just registers hooks - it's safe to run at any point before those hooks fire:

```php
private function __construct() {
    $this->init_hooks(); // add_action('plugins_loaded', [$this, 'setup_plugin'])
    $this->settings = new Presta_Spot_Settings();
    $this->api = new Presta_Spot_Api($this->settings);
    $this->admin = new Presta_Spot_Admin($this->settings);
    $this->admin->init();
}

public function setup_plugin(): void { // on 'plugins_loaded'
    $renderer = new Presta_Spot_Renderer($this->settings, $this->api);
    Presta_Spot_Shortcode::setup($renderer);
    Presta_Spot_Block::setup($renderer); // itself hooks register() to 'init'
    Presta_Spot_Frontend::setup();
}
```

There is no database layer (no custom tables) and no activation hook - nothing needs provisioning on install.

---

## Settings (`Presta_Spot_Settings`)

Pure data access, no hooks, no WordPress admin code. All options are stored as separate `wp_options` rows named `prestaspot_<key>`.

| Constant | Option key | Default | Type |
|---|---|---|---|
| `SHOP_URL` | `shop_url` | `''` | string, `esc_url_raw()` + `untrailingslashit()` |
| `API_KEY` | `api_key` | `''` | string |
| `PRODUCT_COUNT` | `product_count` | `8` | int, min 1 |
| `COLUMNS` | `columns` | `4` | int, min 1 |
| `CACHE_DURATION` | `cache_duration` | `900` | int, min 60 (seconds) |
| `SHOW_IMAGE` | `show_image` | `true` | bool |
| `SHOW_NAME` | `show_name` | `true` | bool |
| `SHOW_DESCRIPTION` | `show_description` | `true` | bool |
| `LAYOUT` | `layout` | `image_name_description` | string enum, see below |

`get_all(): array` fetches and sanitizes every option in one call; `get(string $key)` returns a single value via `get_all()`. There is no per-key `get_option()` fast path - simplicity over micro-optimization, since this runs at most once per render.

### Card layout (`LAYOUT_ELEMENT_ORDER`)

```php
const LAYOUT_ELEMENT_ORDER = array(
    self::LAYOUT_IMAGE_NAME_DESCRIPTION => array('image', 'name', 'description'), // default
    self::LAYOUT_NAME_IMAGE_DESCRIPTION => array('name', 'image', 'description'),
    self::LAYOUT_NAME_DESCRIPTION_IMAGE => array('name', 'description', 'image'),
);
```

This is the single source of truth for both (a) validating a stored/submitted layout value and (b) the actual render order (`Presta_Spot_Settings::get_layout_element_order($layout)`, used by the renderer) and (c) the preview block order in the visual layout picker (both the admin template and `blocks/product-list/index.js` build their picker cards from this same list, so a new layout only needs to be added once, here). The "View in shop" link is not part of this order - it always renders last, unconditionally (see `templates/product-cards.php`).

---

## PrestaShop Webservice Client (`Presta_Spot_Api`)

`get_products(int $limit, int $category_id = 0): array` — returns `[]` immediately if `shop_url` or `api_key` is empty (no request attempted).

**Request**: `GET {shop_url}/api/products?display=[id,name,description_short,link_rewrite,id_default_image]&output_format=JSON&filter[active]=1&limit=0,{limit}` (`&filter[id_category_default]={id}` added when a category filter is given), authenticated via `Authorization: Basic base64(api_key:)` — the PrestaShop webservice convention of using the API key as the Basic Auth username with an empty password.

**Normalization** (`normalize_product()`) maps the raw PrestaShop product into the flat shape every template expects:

```php
['id' => int, 'name' => string, 'description' => string, 'image_url' => string, 'permalink' => string]
```

- **Multilingual fields** (`name`, `description_short`): PrestaShop returns these as a plain string on single-language shops, or as an array of `{id, value}` pairs on multilang/multistore shops. `localized_value()` normalizes both to a string by taking the first language's value - it does **not** respect the current site/shop language. If multi-language support is ever needed, this is the method to extend.
- **Images**: built as `{shop_url}/api/images/products/{id}/{image_id}?ws_key={api_key}` — `ws_key` as a query param (rather than a `Basic` auth header) is required here because this URL is embedded directly in an `<img src>` and loaded by the visitor's browser, which can't send a custom `Authorization` header.
- **Permalink**: always `{shop_url}/index.php?controller=product&id_product={id}` (the ID-based fallback URL) rather than a friendly/rewritten URL — this works regardless of the shop's URL-rewrite configuration, at the cost of not being a "pretty" URL.

**Caching**: results are cached in a transient keyed by `md5(shop_url|limit|category_id)`, TTL from the `cache_duration` setting. There's no cache invalidation beyond TTL expiry — changing shop content only reflects after the cache window passes (or `cache_duration` is lowered).

---

## Rendering (`Presta_Spot_Renderer`)

The single place shortcode and block output are produced, so they can never drift out of sync:

```php
public function render(array $args): string
```

`$args` keys are all optional: `product_count`, `category_id`, `columns`, `show_image`, `show_name`, `show_description`, `layout`. For each, if the caller didn't specify it, the setting's default is used instead.

**Two different "not specified" conventions are used deliberately**, and any new option must pick the right one:

- **Numeric/string options** (`product_count`, `columns`, `layout`): `!empty($args[$key])` - a falsy value (`0`, `''`, unset) means "not specified, use the setting default". This works because `0`/`''` are never valid explicit values for these options.
- **Boolean options** (`show_image`, `show_name`, `show_description`): `array_key_exists($key, $args)` - because `false` **is** a valid explicit value (hide this element) and must be distinguishable from "key absent, use the default". Using `!empty()` here would be a bug: an explicit `false` would be silently treated as "not specified".

The renderer resolves `$element_order` from `Presta_Spot_Settings::get_layout_element_order($layout)` and passes everything to `templates/product-cards.php` via `include` (relies on the including scope's local variables, same pattern DinkyChat uses for its templates).

---

## Card Markup (`templates/product-cards.php`)

For each product, the template iterates `$element_order` and renders whichever of `image` / `name` / `description` is both (a) next in the configured order and (b) enabled via its `$show_*` flag; the "View in shop" link renders unconditionally after the loop.

**Why the image isn't in a separate wrapper**: earlier versions had the image in its own container outside a padded "body" div holding name+description. That broke once the `name_image_description` layout needed the image to render *between* the title and the description - image, title and description are now all direct flex children of `.prestaspot-card`, each handling its own spacing in `assets/css/prestaspot.css`, so they can appear in any order. The image stays edge-to-edge (no horizontal padding) in every position; the "View in shop" link uses `margin-top: auto` to stay pinned to the card bottom regardless of how much content precedes it.

---

## Shortcode (`Presta_Spot_Shortcode`)

`[prestaspot]` → `display_products()`. All attributes default to a sentinel (`0` for numbers, `''` for strings/booleans) via `shortcode_atts()`; `show_image`/`show_name`/`show_description` are only added to the renderer args array when the shortcode attribute wasn't the empty-string sentinel, so omitting them correctly falls through to the settings default rather than being coerced to `false`.

`show_*` values are parsed by `parse_bool()`: anything except `no`/`false`/`0` (case-insensitive) is `true` — so `yes`, `1`, `true`, or simply omitting a recognizable "falsy" word all mean "shown".

---

## Gutenberg Block (`Presta_Spot_Block` + `blocks/product-list/`)

Registered via `register_block_type(PRESTASPOT_PLUGIN_DIR . 'blocks/product-list', ['render_callback' => [$this, 'render']])` on `init`. The `render_callback` argument is what makes this a **dynamic** block - `block.json` has no `render` field, and the block's `save()` returns `null` (nothing is serialized into post content except the attributes).

**No build tooling**: `index.js` is hand-written against the `window.wp.*` globals (`wp.blocks`, `wp.element`, `wp.blockEditor`, `wp.components`, `wp.serverSideRender`, `wp.i18n`) using `element.createElement` directly - no JSX, no webpack, matching DinkyChat's own no-build philosophy for its frontend JS. Because there's no build step to auto-generate an `index.asset.php` (the way `@wordpress/scripts` normally would), `index.asset.php` is **hand-maintained** and must list every `wp-*` script handle the editor script actually uses, or WordPress will enqueue `index.js` without those dependencies loaded first and it will fail silently in the console.

Editor preview uses `<ServerSideRender block="prestaspot/product-list" attributes={...} />`, which calls the same `render_callback` (via the REST API) that produces the frontend output - so the editor and frontend can never visually diverge.

**Attributes** → renderer args mapping (camelCase in the block, snake_case internally): `productCount`→`product_count`, `categoryId`→`category_id`, `columns`, `showImage`→`show_image`, `showName`→`show_name`, `showDescription`→`show_description`, `layout`. Unlike the shortcode, block attributes always have concrete values (block.json `default`s) - there's no "unset" state once a block is inserted, so a block instance doesn't dynamically track later changes to the global settings default; it's a snapshot taken at insertion time, same as any other Gutenberg block attribute.

**Visual layout picker**: both `templates/settings-page.php` and `blocks/product-list/index.js` render the three layout options as radio inputs wrapped in a styled `<label>` (`.prestaspot-layout-option`), with a small CSS-only preview (`.prestaspot-layout-block--image/name/description`, ordered per `LAYOUT_ELEMENT_ORDER`). Selected state is pure CSS (`:has(input:checked)`), no JS needed for the visual state. The two pickers share `assets/css/layout-picker.css` - enqueued directly by `Presta_Spot_Admin::enqueue_admin_scripts()` for the settings page, and via `block.json`'s `editorStyle` field for the block editor. In the block editor, each picker's radio `name` is scoped with the block's `clientId` (`'prestaspot-layout-' + props.clientId`) so multiple block instances on one page can't cross-uncheck each other via native radio-group semantics - defensive, since Gutenberg only mounts one block's `InspectorControls` in the sidebar at a time in practice.

---

## Admin Settings Page (`Presta_Spot_Admin` + `templates/settings-page.php`)

Plain WordPress Settings API - the form POSTs to `options.php`, `settings_fields('prestaspot_settings_group')` handles the nonce, each option is `register_setting()`-ed with its own `sanitize_callback`. No custom AJAX handler, no custom save logic. DinkyChat (the reference project) uses a custom AJAX-based settings save instead, for UX polish its larger scope justifies - PrestaSpot doesn't need that yet; a plain form submit is simpler and sufficient for a handful of fields.

**Checkbox persistence gotcha**: an unchecked HTML checkbox submits no value at all, so `options.php` would silently keep the *old* stored value on uncheck instead of saving `false`. Each boolean field therefore has a same-named `<input type="hidden" value="0">` immediately before the checkbox in the DOM - browsers submit both when checked (`0` then `1`; PHP keeps the *last* value for a repeated field name, i.e. `1`) and only the hidden `0` when unchecked. Any new boolean settings field must follow this pattern or unchecking it will silently do nothing.

---

## Adding a New Setting/Attribute (quick reference)

See `.doc/DEVELOPER_GUIDE.md` for the full worked example. In short, touch: `Presta_Spot_Settings` (constant + default + sanitization in `get_all()`), `Presta_Spot_Admin::register_settings()` (+ `templates/settings-page.php` field), `Presta_Spot_Renderer::render()` (merge logic - pick the right sentinel convention above), `Presta_Spot_Shortcode::display_products()` (shortcode attribute), `Presta_Spot_Block::render()` + `blocks/product-list/block.json` + `index.js` (block attribute + control).

---

**End of Technical Architecture Documentation**
