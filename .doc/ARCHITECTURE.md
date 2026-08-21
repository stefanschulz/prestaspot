# PrestaSpot - Technical Architecture

Technical reference for the PrestaSpot plugin's structure and class contracts, for developers and LLMs working on the codebase.

**Version**: 0.6.0

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
│   ├── product-cards.php                    # Card grid AND list-row markup (view_mode), shared by shortcode and block
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
| `VIEW_MODE` | `view_mode` | `grid` | string enum: `grid` or `list`, see below |
| `LINK_TEXT` | `link_text` | `''` | string, `sanitize_text_field()`. Empty means "use the built-in translated label" - see the shop link section below |
| `LINK_STYLE` | `link_style` | `link` | string enum: `link` or `button` |
| `BUTTON_COLOR` | `button_color` | `#2271b1` | hex color, `sanitize_hex_color()`; only used when `LINK_STYLE` is `button` |

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

`$args` keys are all optional: `product_count`, `category_id`, `columns`, `show_image`, `show_name`, `show_description`, `layout`, `view_mode`, `link_text`, `link_style`, `button_color`. For each, if the caller didn't specify it, the setting's default is used instead.

**Two different "not specified" conventions are used deliberately**, and any new option must pick the right one:

- **Numeric/string options** (`product_count`, `columns`, `layout`, `view_mode`, `link_text`, `link_style`, `button_color`): `!empty($args[$key])` - a falsy value (`0`, `''`, unset) means "not specified, use the setting default". This works because `0`/`''` are never valid explicit values for these options. `view_mode`/`link_style` additionally validate against their `VIEW_MODES`/`LINK_STYLES` arrays (an unrecognized string falls back to the setting default, same as an empty one); `button_color` re-validates via `sanitize_hex_color()` since it can arrive from an untrusted shortcode attribute.
- **Boolean options** (`show_image`, `show_name`, `show_description`): `array_key_exists($key, $args)` - because `false` **is** a valid explicit value (hide this element) and must be distinguishable from "key absent, use the default". Using `!empty()` here would be a bug: an explicit `false` would be silently treated as "not specified".

`link_text` has a further wrinkle: `''` is a legitimate *resolved* value even after falling through instance→settings (meaning neither was customized) - a PHP class constant can't hold a `__()`-translated string, so the built-in "View in shop" label is applied by the template, not the renderer (see below).

The renderer resolves `$element_order` from `Presta_Spot_Settings::get_layout_element_order($layout)` and passes everything to `templates/product-cards.php` via `include` (relies on the including scope's local variables, same pattern DinkyChat uses for its templates).

---

## Card Markup (`templates/product-cards.php`)

The template computes `$prestaspot_visible_elements` once - `$element_order` filtered down to only the elements actually enabled via their `$show_*` flag (not just blanked at render time, *removed*; this matters for the list-mode grouping below). A local closure, `$prestaspot_render_element(string $element, array $product): string`, produces the markup for one element of one product (or `''` if that product has no image/description to show); another, `$prestaspot_render_link`, produces the "View in shop" link. Both the grid and list render paths below call these same two closures, so the actual `<img>`/`<h3>`/`<p>`/`<a>` markup exists exactly once regardless of view mode.

**Grid** (`$view_mode === 'grid'`): straightforward - for each product, loop `$prestaspot_visible_elements` in order and echo each element's markup, then the link.

**List** (`$view_mode === 'list'`): a naive port of the same per-element loop would put every element - image, name, description - as its own sibling in a horizontal flex row, which puts name and description *side by side* instead of stacked, since they're no longer inside a shared vertical "body" the way the grid's `.prestaspot-card` used to have. Instead, `$prestaspot_visible_elements` is walked **once, up front** (not per product) into `$prestaspot_list_groups`: consecutive non-image elements are buffered into one `['type' => 'text', 'elements' => [...]]` group, and each `image` becomes its own `['type' => 'image']` group. For each product, the groups are rendered in order; a `text` group is wrapped in a `.prestaspot-list-item-text` column (name+description stacked *within* that column), while `image` renders directly as its own row-level flex child. Concretely, for the three `layout` values:

| `layout` | Groups produced |
|---|---|
| `image_name_description` (default) | `[image]`, `[text: name, description]` - classic thumbnail + text block |
| `name_image_description` | `[text: name]`, `[image]`, `[text: description]` - three columns, image in the middle |
| `name_description_image` | `[text: name, description]`, `[image]` - text block + thumbnail on the right |

If `show_image` is off (or a specific product just has no image), that whole `image` group is absent for that render, which also means a `name`+`description` pair that would otherwise have been split by the image (the `name_image_description` case) correctly merges back into one contiguous text group instead of leaving a gap. This is exactly what makes "no image → row sizes itself to the text" (a stated requirement) fall out naturally: there's no reserved image column to begin with, not just an empty one hidden by CSS.

**Why the image isn't in a separate wrapper at the element-styling level**: `assets/css/prestaspot.css` styles `.prestaspot-card-image`/`-title`/`-description`/`-link` as reusable element classes, not nested in a card-specific "body" div - both view modes, and any position the image ends up in via `layout`, share the same four classes. The grid uses `margin-top: auto` on the link to stay pinned to the card bottom (column flex); the list overrides this to `margin-left: auto` (row flex) so the link instead pins to the row's *right* end - see the "List view" block in `prestaspot.css` for the full set of overrides `.prestaspot-list-item` needs (smaller image, `.prestaspot-list-item-text` as its own flex column, reset paddings that assumed a vertical card).

### Shop Link (`$prestaspot_render_link`)

Built once per render (not inside the element closure above), since its label/style/color don't vary by product:

```php
$prestaspot_link_label = '' !== $link_text ? $link_text : __('View in shop', 'prestaspot');
```

This is where the translated-default fallback described in the Renderer section actually applies - `$link_text` reaching the template as `''` means neither the instance nor the setting customized it.

When `$link_style === 'button'`, the closure adds the `prestaspot-card-link--button` class and an inline `style="background-color: ...; color: ...;"` - the background is the admin-configured `$button_color` directly, and the text color comes from `Presta_Spot_Renderer::get_contrasting_text_color($button_color)`, a static method (not tied to any instance) so the template can call it without holding a `Presta_Spot_Renderer` object. Its brightness formula (`(r*299 + g*587 + b*114) / 1000`, threshold 128) is carried over unchanged from `Dinky_Chat::get_contrasting_text_color()` in the reference project, which uses it for the same purpose (picking readable text over an admin-configured background color). Plain `link` style gets neither the class nor any inline style - it's styled purely via the static `.prestaspot-card-link` rule in `prestaspot.css`.

---

## Shortcode (`Presta_Spot_Shortcode`)

`[prestaspot]` → `display_products()`. All attributes default to a sentinel (`0` for numbers, `''` for strings/booleans, including `layout`, `view_mode`, `link_text`, `link_style`, `button_color`) via `shortcode_atts()`; `show_image`/`show_name`/`show_description` are only added to the renderer args array when the shortcode attribute wasn't the empty-string sentinel, so omitting them correctly falls through to the settings default rather than being coerced to `false`.

`show_*` values are parsed by `parse_bool()`: anything except `no`/`false`/`0` (case-insensitive) is `true` — so `yes`, `1`, `true`, or simply omitting a recognizable "falsy" word all mean "shown".

---

## Gutenberg Block (`Presta_Spot_Block` + `blocks/product-list/`)

Registered via `register_block_type(PRESTASPOT_PLUGIN_DIR . 'blocks/product-list', ['render_callback' => [$this, 'render']])` on `init`. The `render_callback` argument is what makes this a **dynamic** block - `block.json` has no `render` field, and the block's `save()` returns `null` (nothing is serialized into post content except the attributes).

**No build tooling**: `index.js` is hand-written against the `window.wp.*` globals (`wp.blocks`, `wp.element`, `wp.blockEditor`, `wp.components`, `wp.serverSideRender`, `wp.i18n`) using `element.createElement` directly - no JSX, no webpack, matching DinkyChat's own no-build philosophy for its frontend JS. Because there's no build step to auto-generate an `index.asset.php` (the way `@wordpress/scripts` normally would), `index.asset.php` is **hand-maintained** and must list every `wp-*` script handle the editor script actually uses, or WordPress will enqueue `index.js` without those dependencies loaded first and it will fail silently in the console.

Editor preview uses `<ServerSideRender block="prestaspot/product-list" attributes={...} />`, which calls the same `render_callback` (via the REST API) that produces the frontend output - so the editor and frontend can never visually diverge.

**Attributes** → renderer args mapping (camelCase in the block, snake_case internally): `productCount`→`product_count`, `categoryId`→`category_id`, `columns`, `showImage`→`show_image`, `showName`→`show_name`, `showDescription`→`show_description`, `layout`, `viewMode`→`view_mode`, `linkText`→`link_text`, `linkStyle`→`link_style`, `buttonColor`→`button_color`. Unlike the shortcode, block attributes always have concrete values (block.json `default`s) - there's no "unset" state once a block is inserted, so a block instance doesn't dynamically track later changes to the global settings default; it's a snapshot taken at insertion time, same as any other Gutenberg block attribute. `linkText` is the one exception worth noting: its block.json default is `""` (not a hardcoded "View in shop"), specifically so a freshly inserted block still resolves to the *translated* built-in label via the template, rather than baking English text into every new block.

**Visual pickers**: both `templates/settings-page.php` and `blocks/product-list/index.js` render three radio-based pickers sharing the same `.prestaspot-layout-picker`/`.prestaspot-layout-option` shell (a styled `<label>` wrapping a real radio input, so keyboard/label-click semantics come for free) but different preview content:

- **Card Layout** (element order): `.prestaspot-layout-preview` with `.prestaspot-layout-block--image/name/description` bars, ordered per `LAYOUT_ELEMENT_ORDER`.
- **Display Mode** (`view_mode`): `.prestaspot-viewmode-preview` with a small 2×2 grid of squares (`--grid`) or three stacked bars (`--list`) - see `VIEW_MODE_OPTIONS` in `index.js` and the parallel markup in `settings-page.php`.
- **Shop Link Style** (`link_style`): `.prestaspot-linkstyle-preview` with an underlined bar (`--link`) or a filled swatch (`--button`) - the button swatch's background is set inline to the *actual currently-configured* `button_color`, not a fixed preview color, so the picker doubles as a live color check.

Selected state is pure CSS (`:has(input:checked)`), no JS needed for the visual state. All three pickers share `assets/css/layout-picker.css` (the name predates the later pickers but wasn't worth renaming/splitting for a few more small rulesets) - enqueued directly by `Presta_Spot_Admin::enqueue_admin_scripts()` for the settings page, and via `block.json`'s `editorStyle` field for the block editor. In the block editor, each picker's radio `name` is scoped with the block's `clientId` (e.g. `'prestaspot-layout-' + props.clientId`) so multiple block instances on one page can't cross-uncheck each other via native radio-group semantics - defensive, since Gutenberg only mounts one block's `InspectorControls` in the sidebar at a time in practice.

**Button color control**: unlike the other pickers, the actual color value is set via `wp.blockEditor.PanelColorSettings` (the same component core blocks like Paragraph/Button use for their color settings), not a custom control - unlike a 2-3 option enum, "pick any color" isn't a good fit for the visual-card-picker pattern. It's rendered as its own panel, conditionally (`'button' === attributes.linkStyle && el(PanelColorSettings, {...})`) - only shown once Link Style is set to Button. The settings page (plain PHP form, no reactive show/hide) instead always shows the native `<input type="color">` field with a "only used when Shop Link Style is Button" description, matching how `columns`/`view_mode` handle the same kind of conditional relevance.

---

## Admin Settings Page (`Presta_Spot_Admin` + `templates/settings-page.php`)

Plain WordPress Settings API - the form POSTs to `options.php`, `settings_fields('prestaspot_settings_group')` handles the nonce, each option is `register_setting()`-ed with its own `sanitize_callback`. No custom AJAX handler, no custom save logic. DinkyChat (the reference project) uses a custom AJAX-based settings save instead, for UX polish its larger scope justifies - PrestaSpot doesn't need that yet; a plain form submit is simpler and sufficient for a handful of fields.

**Checkbox persistence gotcha**: an unchecked HTML checkbox submits no value at all, so `options.php` would silently keep the *old* stored value on uncheck instead of saving `false`. Each boolean field therefore has a same-named `<input type="hidden" value="0">` immediately before the checkbox in the DOM - browsers submit both when checked (`0` then `1`; PHP keeps the *last* value for a repeated field name, i.e. `1`) and only the hidden `0` when unchecked. Any new boolean settings field must follow this pattern or unchecking it will silently do nothing.

---

## Adding a New Setting/Attribute (quick reference)

See `.doc/DEVELOPER_GUIDE.md` for the full worked example. In short, touch: `Presta_Spot_Settings` (constant + default + sanitization in `get_all()`), `Presta_Spot_Admin::register_settings()` (+ `templates/settings-page.php` field), `Presta_Spot_Renderer::render()` (merge logic - pick the right sentinel convention above), `Presta_Spot_Shortcode::display_products()` (shortcode attribute), `Presta_Spot_Block::render()` + `blocks/product-list/block.json` + `index.js` (block attribute + control).

---

**End of Technical Architecture Documentation**
