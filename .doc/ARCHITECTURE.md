# PrestaSpot - Technical Architecture

Technical reference for the PrestaSpot plugin's structure and class contracts, for developers and LLMs working on the codebase.

**Version**: 0.16.1

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
├── assets/
│   ├── css/
│   │   ├── prestaspot.css                   # Card grid + card styles (frontend, and block "style")
│   │   ├── layout-picker.css                # Visual layout picker (shared by settings page and block editor)
│   │   └── settings-page.css                # Per-field reset button styling + color picker hiding (settings page only)
│   └── js/
│       ├── settings-page.js                 # Per-field reset button behavior (settings page only)
│       └── settings-color-picker.js         # Mounts wp.components.ColorPalette over the color fields (settings page only)
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
    Presta_Spot_Block::setup($renderer, $this->api); // itself hooks register()+register_rest_routes() to 'init'/'rest_api_init'
    Presta_Spot_Frontend::setup();
}
```

`Presta_Spot_Block` takes `$this->api` directly (not just via the renderer) since 0.13.0 - it needs it to expose category data to the block editor over REST (see the Gutenberg Block section below), which has nothing to do with rendering a card.

There is no database layer (no custom tables) and no activation hook - nothing needs provisioning on install.

---

## Settings (`Presta_Spot_Settings`)

Pure data access, no hooks, no WordPress admin code. All options are stored as separate `wp_options` rows named `prestaspot_<key>`.

| Constant | Option key | Default | Type |
|---|---|---|---|
| `SHOP_URL` | `shop_url` | `''` | string, `esc_url_raw()` + `untrailingslashit()`, strips a trailing `/api` |
| `API_KEY` | `api_key` | `''` | string |
| `PRODUCT_COUNT` | `product_count` | `8` | int, min 1 |
| `COLUMNS` | `columns` | `4` | int, min 1 |
| `CACHE_DURATION` | `cache_duration` | `900` | int, min 60 (seconds) |
| `SHOW_IMAGE` | `show_image` | `true` | bool |
| `SHOW_NAME` | `show_name` | `true` | bool |
| `SHOW_DESCRIPTION` | `show_description` | `true` | bool |
| `SHOW_PRICE` | `show_price` | `true` | bool |
| `SHOW_STOCK_STATUS` | `show_stock_status` | `true` | bool; only actually renders when the shop tracks stock, see below |
| `PRICE_POSITION` | `price_position` | `after_name` | string enum: `after_name` or `after_description`, see below |
| `LAYOUT` | `layout` | `image_name_description` | string enum, see below |
| `VIEW_MODE` | `view_mode` | `grid` | string enum: `grid` or `list`, see below |
| `LINK_TEXT` | `link_text` | `''` | string, `sanitize_text_field()`. Empty means "use the built-in translated label" - see the shop link section below |
| `LINK_STYLE` | `link_style` | `link` | string enum: `link` or `button` |
| `BUTTON_COLOR` | `button_color` | `#2271b1` | hex color, `sanitize_hex_color()`; only used when `LINK_STYLE` is `button` |
| `SALE_BADGE_COLOR` | `sale_badge_color` | `#e63946` | hex color, `sanitize_hex_color()`; background of the sale ribbon/badge, see below |
| `SORT` | `sort` | `''` | string enum: `''` (PrestaShop's own, unspecified order) or `name_asc`/`name_desc`/`price_asc`/`price_desc`/`date_asc`/`date_desc`, see below |

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

**Price is not part of this constant.** Rather than being a fourth position in every `LAYOUT_ELEMENT_ORDER` permutation - which would multiply the number of layout choices (3 layouts × price position) for comparatively little benefit - `Presta_Spot_Renderer::render()` splices `'price'` into the resolved `$element_order` array at render time, right after whichever element `PRICE_POSITION` points at:

```php
$price_anchor = Presta_Spot_Settings::PRICE_POSITION_AFTER_DESCRIPTION === $price_position ? 'description' : 'name';
array_splice($element_order, array_search($price_anchor, $element_order, true) + 1, 0, ['price']);
```

Since `name` and `description` both always appear in every `LAYOUT_ELEMENT_ORDER` permutation regardless of layout, this works unconditionally without needing a "anchor not found" fallback. `PRICE_POSITION` has its own two-value picker (`PRICE_POSITION_AFTER_NAME`/`PRICE_POSITION_AFTER_DESCRIPTION`, validated via `PRICE_POSITIONS`), independent of `layout` - so e.g. `layout=name_image_description` with `price_position=after_description` still puts price last (name, image, description, price), not between name and image. Price's *visibility* is controlled by `$show_price` through the same `$prestaspot_visible_elements` filter as the other elements (see below), same as before - only its position is now configurable.

---

## PrestaShop Webservice Client (`Presta_Spot_Api`)

`get_products(int $limit, int $category_id = 0, bool $on_sale = false, string $sort = ''): array` — returns `[]` immediately if `shop_url` or `api_key` is empty (no request attempted).

**Request**: `GET {shop_url}/api/products?display=[id,name,description_short,link_rewrite,id_default_image,price,on_sale]&output_format=JSON&filter[active]=1&limit=0,{limit}` (`&filter[id_category_default]={id}` added when a category filter is given, `&filter[on_sale]=1` when the `on_sale` argument is true, `&sort=field_DIRECTION` (`+&date=1` for date fields) when `$sort` is non-empty - see below, `&language={id}` when a language was resolved - see below), authenticated via `Authorization: Basic base64(api_key:)` — the PrestaShop webservice convention of using the API key as the Basic Auth username with an empty password.

**Normalization** (`normalize_product()`) maps the raw PrestaShop product into the flat shape every template expects:

```php
['id' => int, 'name' => string, 'description' => string, 'image_url' => string, 'permalink' => string, 'price' => string, 'on_sale' => bool, 'stock_status' => string]
```

`price` is pre-formatted (amount + currency symbol, e.g. `"23.90 €"`) via `format_price()`, not a raw float - the template only ever needs to echo it. It's built from the `price` field's raw value (always tax-excluded in PrestaShop) and `get_shop_currency()`'s symbol/precision (see below); empty string if the `price` field was missing from the response for some reason (template hides the price element entirely in that case, same as a missing `description`).

`on_sale` is `products.on_sale` cast to bool (`!empty($product['on_sale'])` - the webservice returns it as the string `"0"`/`"1"`) - this is what the `templates/product-cards.php` badge below is driven by, independent of whether the `filter[on_sale]` argument was used for this particular request.

**`on_sale` is shop-scoped, not the base `ps_product` row** - worth knowing if you're editing this field directly in the DB rather than through PrestaShop's admin UI (see the dev-fixture note in `.doc/DEVELOPER_GUIDE.md`, discovered exactly this way): `Product::$definition['fields']['on_sale']` has `'shop' => true`, meaning `Product::on_sale` and this webservice display field both actually read `ps_product_shop.on_sale`. `ps_product.on_sale` still exists as a column but isn't what's read here - a raw `UPDATE ps_product SET on_sale=1` was observed to make `filter[on_sale]=1` match the product while the returned `on_sale` display field for that same product still read back `"0"`, until `ps_product_shop.on_sale` was updated too. Exactly why the filter and the display field disagreed isn't confirmed (not worth digging into PrestaShop's filter SQL for a dev-fixture quirk) - but updating both is the fix and avoids the question.

- **Multilingual fields** (`name`, `description_short`): PrestaShop returns these as a plain string when the request was scoped to a single language via `language=`, or as an array of `{id, value}` pairs (one per configured shop language) when it wasn't. `localized_value()` normalizes both shapes to a string, taking the first language's value in the array case - this array case is now mainly a fallback (no Polylang, or no matching shop language), not the primary path.
- **Images**: built as `{shop_url}/api/images/products/{id}/{image_id}?ws_key={api_key}` — `ws_key` as a query param (rather than a `Basic` auth header) is required here because this URL is embedded directly in an `<img src>` and loaded by the visitor's browser, which can't send a custom `Authorization` header.
- **Permalink**: always `{shop_url}/index.php?controller=product&id_product={id}` (the ID-based fallback URL) rather than a friendly/rewritten URL — this works regardless of the shop's URL-rewrite configuration, at the cost of not being a "pretty" URL.

**Caching**: results are cached in a transient keyed by `md5(shop_url|limit|category_id|language_id|on_sale|sort)` (the resolved language id, `0` if none - see below), TTL from the `cache_duration` setting; the language id, `on_sale`, and `sort` are all part of the key specifically so different languages, sale-filtered vs. unfiltered, or differently-sorted requests never share a stale cache entry. There's no cache invalidation beyond TTL expiry — changing shop content only reflects after the cache window passes (or `cache_duration` is lowered). Stock quantities aren't a separate cache key - they're fetched once per cache-miss render and baked into the cached product array, same as price/currency.

### Stock status (`is_stock_managed()`, `get_stock_quantities()`)

**`products.quantity` doesn't work** - confirmed by reading PrestaShop's own `Product` class source: its `webserviceParameters` entry is `['getter' => false, 'setter' => false]`, meaning the webservice just reads the plain `$this->quantity` property directly rather than computing it - and that property is never populated during the generic object-hydration path the webservice uses to build a `/api/products` response (stock lives in a separate table, `ps_stock_available`, not a `ps_product` column). Live testing confirmed this: a product with `quantity=300` in the database consistently read back `0` via `/api/products`, on both the collection and single-resource endpoints. So `display=[...]` never requests `quantity` at all - it would just be zero for every product, indistinguishable from genuinely being out of stock.

Real quantities come from the dedicated `stock_availables` resource instead, fetched in bulk (`get_stock_quantities(string $shop_url, string $api_key, int[] $product_ids): array`, returning `id_product => quantity`) via one extra request per cache-miss render:

```
GET {shop_url}/api/stock_availables?display=[id_product,quantity]&filter[id_product]=[1|2|3]&filter[id_product_attribute]=0
```

`filter[id_product]=[...]` is PrestaShop's OR-list filter syntax (pipe-separated), confirmed working for an arbitrary set of ids in one call - avoids an N+1 request per product. `id_product_attribute=0` is PrestaShop's own maintained aggregate stock row per product: for a simple product it's that product's own quantity; for one with combinations, it's kept in sync as the sum across all combinations (confirmed live: a product with 8 combinations of 300 each read back exactly 2400 at `id_product_attribute=0`) - so the same filter value works correctly for both cases without needing to know which type a product is. Requires the Webservice API key to have GET access to `stock_availables`; on any failure the map is empty and every product's `stock_status` falls back to `''` (not shown), same fallback as everything else in this file.

`is_stock_managed(string $shop_url, string $api_key): bool` gates the whole feature on PrestaShop's own shop-wide "Enable stock management" setting (`PS_STOCK_MANAGEMENT`, fetched from `GET {shop_url}/api/configurations?filter[name]=PS_STOCK_MANAGEMENT`, cached a day like the other shop-level flags) - **not** just whether `stock_availables` happens to return a number. A shop can have stock management enabled but simply never bother updating quantities for products that aren't really being tracked (a real, observed state on the dev demo shop - `PS_STOCK_MANAGEMENT=1` yet every product's quantity sat at an unpopulated `0` from the initial import); this flag is the one clean signal PrestaShop itself exposes for "is stock actually meant to be tracked here" per the plugin's design goal of not showing a false "Out of Stock" off data nobody maintains. `get_stock_quantities()` is only called at all when this returns `true` - defaults to `false` (don't show stock status) on any failure, since a wrongly-shown "Out of Stock" is worse than not showing the indicator. Requires GET access to `configurations`.

`normalize_product()`'s `stock_status` is `'in_stock'`/`'out_of_stock'` (quantity `> 0`/`<= 0`) when a quantity was found for that product, `''` otherwise (stock not managed shop-wide, or this specific product's id wasn't in the bulk response for some reason) - the template hides the element entirely in the `''` case, same pattern as a missing `description`/`price`.

### Sort (`build_sort_args()`)

`Presta_Spot_Api::build_sort_args(string $sort): array` maps the setting's short values to the webservice's actual query params via a lookup table (`Presta_Spot_Settings::SORT_NAME_ASC => ['name', 'ASC']`, etc.): `''` (nothing matched, or `SORT_DEFAULT`) returns `[]` - no `sort=` param at all, PrestaShop's own unspecified order, same as before this setting existed. Otherwise it builds `sort={field}_{ASC|DESC}`, and for the two `date_add` variants, also adds `date=1` - confirmed via live testing that the webservice otherwise rejects `date_add` outright ("Unable to filter by this field") since date fields aren't sortable/filterable by default without that flag.

Sorting by `price` needed no special handling (it's a plain numeric column, same field `format_price()` already reads). Sorting by `name`, though, sorts on a *multilingual* field - see the language-fallback fix directly below, which this depends on to avoid a 500 on shops with incomplete translation coverage.

### Price and currency (`format_price()`, `get_shop_currency()`)

PrestaShop's webservice has a documented tax/reduction-aware computed-price mechanism (`GET /api/products/2?price[my_alias][use_tax]=1`, with further params for reductions, quantity, currency, etc.), but live testing against a real PrestaShop instance showed it **only works when fetching a single product by id** - adding the same `price[alias][...]` query params to the `/api/products` *collection* request is silently ignored (no price fields appear in the response, no error). Computing it per-product would mean one extra HTTP request per product on every cache-miss render, which isn't worth the cost for a lightweight display widget - so PrestaSpot instead uses the plain `price` field already available on the list request. This is always **tax-excluded** (PrestaShop's raw base price); there's no tax-inclusive or reduction-applied variant available in bulk, so what's shown is the shop's base catalog price, not a checkout-accurate price.

`get_shop_currency(string $shop_url, string $api_key): array` (`{symbol: string, precision: int}`) fetches `GET {shop_url}/api/currencies?display=[symbol,precision]&filter[active]=1&limit=0,1`, scoped with `&language={id}` (the first available shop language, from `get_shop_languages()` - not tied to the visitor's resolved language, any valid language id avoids the bug below). Two things learned from live testing that aren't obvious from the docs:

- The webservice exposes no "this is the shop's default currency" flag on the `currencies` resource, so this just takes the first *active* one - correct for the common single-currency shop, best-effort for a genuinely multi-currency one.
- `symbol` is itself a multilingual field (`localized_value()` handles it, same as `name`/`description_short`) - and critically, requesting it **without** the `language=` scope on a shop with more than one configured language, where the symbol isn't translated into all of them, makes the webservice's own JSON serialization choke on the untranslated (null) entry and return an HTTP 500 - even though the payload underneath (once decoded) is perfectly usable. Always passing `language=` avoids this entirely.

Cached in its own transient (`prestaspot_currency_{md5(shop_url)}`) for `DAY_IN_SECONDS`, same as `get_shop_languages()` - a shop's currency essentially never changes. Requires the Webservice API key to additionally have GET access to the `currencies` resource; without it (or on any other failure), falls back to `{symbol: '', precision: 2}` - `format_price()` then renders a bare number with no currency symbol rather than breaking the card.

### Categories (`get_categories()`, `resolve_category_id_by_name()`)

`public function get_categories(): array` (`[{id: int, name: string}, ...]`) is the odd one out among these shop-reference-data getters - `get_shop_languages()`/`get_shop_currency()` are `private`, but this one is `public` because it has two independent consumers: `resolve_category_id_by_name()` right below, and `Presta_Spot_Block::get_categories_route()` (the REST endpoint backing the block editor's category picker - see the Gutenberg Block section). Same shape otherwise: `GET {shop_url}/api/categories?display=[id,name]&filter[active]=1`, scoped with `&language=` (first shop language) for the same untranslated-multilingual-field-crashes-the-webservice reason `get_shop_currency()`'s `symbol` field has - `name` is multilingual here too. Results are sorted alphabetically (`strnatcasecmp`) before returning, since this list is meant to populate a picker, not mirror the shop's raw category tree order. Cached like currency/languages (`prestaspot_categories_{md5(shop_url)}`, `DAY_IN_SECONDS`); requires the Webservice API key to have GET access to `categories`, falls back to `[]` on any failure (empty shop config, missing permission, network error) - callers treat that the same as "no categories available", not an error condition.

Note: PrestaShop's own structural categories ("Root", "Home") come back in this list alongside real product categories - the webservice has no clean way to distinguish them, and their IDs aren't fixed/predictable enough to filter out reliably. Harmless in practice (an admin just won't pick them), not worth the complexity of guessing which entries to exclude.

`public function resolve_category_id_by_name(string $name): int` - case-insensitive exact match against `get_categories()`, first match wins if a shop somehow has duplicate category names under different parents, `0` (= no category filter) if nothing matches. Used by `Presta_Spot_Renderer::render()` to support the shortcode's `category_name` attribute (see below) - the block doesn't need this at all, since its dropdown already resolves straight to a numeric `categoryId` client-side.

### Manual Language Override (`get_languages()`, `resolve_language_id_by_code()`)

Same shape and role as `get_categories()`/`resolve_category_id_by_name()` above, for letting a block/shortcode instance pick a specific shop language instead of relying purely on automatic Polylang/WPML detection (see the next section) - useful on a site running neither plugin, or for a page that deliberately wants a different shop language than its own. `public function get_languages(): array` (`[{id: int, name: string, iso_code: string}, ...]`) is a thin public wrapper around the already-`private`/cached `get_shop_languages()` (adds `name` to its `display=[...]`, previously just `[id,iso_code]`) - backs both `Presta_Spot_Block::get_languages_route()` (the block editor's language picker) and `resolve_language_id_by_code()`. Unlike category/currency names, a language's own `name` field isn't itself a multilingual/translatable value (each language resource entry just has one plain string), so there's no untranslated-field-crashes-the-webservice concern here.

`public function resolve_language_id_by_code(string $code): int` - case-insensitive match against `iso_code` (PrestaShop's own, not always the ISO code you'd expect - e.g. English can be `gb`), `0` (= fall through to automatic detection) if nothing matches. Used by `Presta_Spot_Renderer::render()` for the shortcode's `language` attribute, same `language_id`-wins-if-both-given precedence as `category_id`/`category_name`. `Presta_Spot_Api::get_products()` takes the resolved id as `$language_override_id`; when `> 0` it's used as-is, skipping `resolve_language_id()`/the automatic-detection fallback chain below entirely.

### Multilingual plugin language sync (`resolve_language_id()`)

Product data is requested in the PrestaShop language matching the page's current language - as reported by whichever supported multilingual plugin is active (Polylang, then WPML) - so multilingual sites don't always see the shop's default-language text. Fully additive/optional - degrades gracefully to the pre-0.7.0 behavior (the array-normalization case above) when no supported plugin is active or no shop language matches:

```php
private function resolve_language_id(string $shop_url, string $api_key): int
{
    $iso_code = $this->get_current_language_iso_code(); // '' if neither plugin is active
    if ('' === $iso_code) {
        return 0;
    }
    foreach ($this->get_shop_languages($shop_url, $api_key) as $language) {
        if ($iso_code === $language['iso_code']) {
            return $language['id'];
        }
    }
    return 0; // no PrestaShop language matches the current site language
}

private function get_current_language_iso_code(): string
{
    $iso_code = $this->get_current_polylang_iso_code();
    return '' !== $iso_code ? $iso_code : $this->get_current_wpml_iso_code();
}
```

Polylang is tried first, WPML second; both running at once isn't a realistic scenario outside a migration, so a simple first-match order is fine rather than needing to reconcile the two.

- **Polylang**: `pll_current_language('locale')` (not the default `pll_current_language()`, which returns Polylang's admin-editable URL *slug* - not guaranteed to be an ISO 639-1 code at all, e.g. an admin could rename it to `"deutsch"`) truncated to its first 2 characters. The WordPress locale is tied to the site's actual translation files rather than a customizable setting, so it's the more reliable source for a real ISO 639-1 code. Guarded by `function_exists('pll_current_language')`.
- **WPML**: same underlying problem, different cause - WPML's own language codes aren't guaranteed 2-letter ISO 639-1 either (e.g. `zh-hans`, or a fully custom admin-defined code), so the same locale-based approach is used: `apply_filters('wpml_current_language', null)` gets the current code, then that code is looked up in `apply_filters('wpml_active_languages', null, ['skip_missing' => 0])` for its `default_locale` (WordPress-style, e.g. `de_DE`), truncated the same way. Guarded by `defined('ICL_SITEPRESS_VERSION')` - `apply_filters()` on an unregistered filter just returns the given default (`null`) rather than erroring, but the constant check avoids doing the (harmless but pointless) double filter call when WPML isn't there at all.
- **Shop languages**: `GET {shop_url}/api/languages?display=[id,iso_code]` - **not** `filter[id_lang]` (that key doesn't exist for this purpose; PrestaShop's language scoping is the separate top-level `language` parameter used in the products request above). Requires the Webservice API key to additionally have GET access to the `languages` resource - if it doesn't, the request 403s, `get_shop_languages()` returns `[]`, and language sync silently falls back to off (same as no multilingual plugin) rather than breaking product fetching. Cached in its own transient (`prestaspot_languages_{md5(shop_url)}`) for `DAY_IN_SECONDS`, independent of `cache_duration`, since a shop's configured languages practically never change - much more stable than the product list.
- **Matching**: case-sensitive-safe compare of two already-lowercased 2-letter strings (both ISO-code-resolving methods and `get_shop_languages()` lowercase their output before comparing/caching).

### Unscoped-request fallback (bug fix, `get_products()`)

`resolve_language_id()` itself still returns `0` when neither plugin is active or nothing matches - that contract is unchanged. But `get_products()` (not `resolve_language_id()`) now treats a `0` result as "try the shop's first available language before giving up", not as "leave the request unscoped":

```php
$language_id = $this->resolve_language_id($shop_url, $api_key);
if (0 === $language_id) {
    $shop_languages = $this->get_shop_languages($shop_url, $api_key);
    if (!empty($shop_languages)) {
        $language_id = $shop_languages[0]['id'];
    }
}
```

**Why**: confirmed via live testing (deliberately deactivating Polylang against a shop with a second, incompletely-translated language) that a genuinely unscoped request for a multilingual field - `name`, in particular - makes the webservice's own JSON serialization choke on the untranslated (null) entry and return an HTTP 500, exactly the same underlying quirk `get_shop_currency()` already had to work around for the `symbol` field (see above). `fetch_products()` treats any non-200 response as "no products" - so before this fix, a shop with 2+ configured languages and incomplete translation coverage could silently show an empty product list any time no multilingual plugin resolved a language for the current request. This predates and is independent of the `sort` feature; it was found *while* testing `sort=name_asc` (which exercises the exact same code path) but affects the plain, unsorted product listing equally.

Same permission dependency as `get_shop_currency()`: this fallback only works when the Webservice API key has GET access to `languages` (see `get_shop_languages()` above) - without it, behavior is unchanged from before (unscoped request), which remains safe for a single-language shop and only actually breaks for a multi-language one that also happens to have incomplete translations, i.e., no regression for the common case either way.

---

## Rendering (`Presta_Spot_Renderer`)

The single place shortcode and block output are produced, so they can never drift out of sync:

```php
public function render(array $args): string
```

`$args` keys are all optional: `product_count`, `category_id`, `category_name`, `language_id`, `language`, `on_sale`, `sort`, `columns`, `show_image`, `show_name`, `show_description`, `show_price`, `show_stock_status`, `price_position`, `layout`, `view_mode`, `link_text`, `link_style`, `button_color`, `sale_badge_color`. For each, if the caller didn't specify it, the setting's default is used instead.

**Two different "not specified" conventions are used deliberately**, and any new option must pick the right one:

- **Numeric/string options** (`product_count`, `columns`, `layout`, `price_position`, `view_mode`, `sort`, `link_text`, `link_style`, `button_color`, `sale_badge_color`): `!empty($args[$key])` - a falsy value (`0`, `''`, unset) means "not specified, use the setting default". This works because `0`/`''` are never valid explicit values for these options. `view_mode`/`link_style`/`price_position`/`sort` additionally validate against their `VIEW_MODES`/`LINK_STYLES`/`PRICE_POSITIONS`/`SORTS` arrays (an unrecognized string falls back to the setting default, same as an empty one); `button_color`/`sale_badge_color` re-validate via `sanitize_hex_color()` since they can arrive from an untrusted shortcode attribute.
- **Boolean options** (`show_image`, `show_name`, `show_description`, `show_price`, `show_stock_status`): `array_key_exists($key, $args)` - because `false` **is** a valid explicit value (hide this element) and must be distinguishable from "key absent, use the default". Using `!empty()` here would be a bug: an explicit `false` would be silently treated as "not specified".

`category_id` and `on_sale` are the odd ones out: both are instance-only filters with **no** global setting to fall back to (there's nothing sensible a site-wide "default category" or "default on-sale-only" would mean) - `category_id` already established this precedent (`absint($args['category_id'] ?? 0)`, `0` = no filter), and `on_sale` follows it exactly (`!empty($args['on_sale'])`, unset/false = no filter).

`category_name` isn't its own independent arg in this sentinel sense - it's only ever consulted as a fallback for `category_id`, and only by the shortcode (see below):

```php
$category_id = absint($args['category_id'] ?? 0);
if (0 === $category_id && !empty($args['category_name'])) {
    $category_id = $this->api->resolve_category_id_by_name((string)$args['category_name']);
}
```

An explicit numeric `category_id` always wins if both are somehow given; a `category_name` that doesn't resolve to anything (typo, wrong case doesn't matter since matching is case-insensitive, but a genuinely nonexistent name) leaves `$category_id` at `0`, i.e. silently falls through to "no category filter" rather than erroring - consistent with every other best-effort lookup in this codebase (currency, language matching).

`language_id`/`language` mirror this exact same pattern one setting later - `absint($args['language_id'] ?? 0)`, then `resolve_language_id_by_code()` as the fallback for a non-empty `language` - except `0` here means "fall through to automatic Polylang/WPML detection" (see below) rather than "no filter"; there's no way to request "PrestaShop's own unspecified language order" the way `category_id`'s `0` means "no category filter", since a language always has to resolve to *something* before the request can be made.

`link_text` and `sort` both have a further wrinkle in common: `''` is a legitimate *resolved* value even after falling through instance→settings (meaning neither was customized), not just an intermediate sentinel. For `link_text` it means "use the built-in translated label" (a PHP class constant can't hold a `__()`-translated string, so the template applies it, not the renderer - see below). For `sort` it means "PrestaShop's own, unspecified order" - a real, useful choice in its own right (`SORT_DEFAULT`), not a placeholder for "not decided yet".

The renderer resolves `$element_order` from `Presta_Spot_Settings::get_layout_element_order($layout)` and passes everything to `templates/product-cards.php` via `include` (relies on the including scope's local variables, same pattern DinkyChat uses for its templates).

`'stock'` is spliced into `$element_order` right after `'price'` (post-splice, so it lands correctly regardless of `price_position`) - unlike price, it has no position setting of its own; always immediately follows the price it's contextually about.

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

### Sale Indicator (corner ribbon + `$prestaspot_render_badge` fallback)

Driven purely by whether *that product* is flagged `on_sale` in PrestaShop - not by whether the `on_sale` filter argument was used for the request, so it also lights up on an unfiltered "all products" listing when one of the shown products happens to be on sale. Not part of the reorderable/toggleable element system on purpose - it's a status indicator, not a content element like name/description/price, so it doesn't need a position or a visibility setting the way those do.

Two renderings, depending on whether an image is actually shown for that product:

- **With image**: a `<span class="prestaspot-card-ribbon">Sale</span>` is embedded directly inside the `<a class="prestaspot-card-image">` markup returned by `$prestaspot_render_element('image', ...)` (not a separate closure call) - it needs to live *inside* that link so `.prestaspot-card-image`'s `overflow: hidden` clips it into a clean diagonal strip, and `position: relative` on that same element anchors it. CSS does the rest: `position: absolute`, fixed width, `transform: rotate(-45deg)`, positioned to start past the image's left edge so the rotated strip's ends land outside the visible box and get clipped instead of dangling. `.prestaspot-list-item .prestaspot-card-ribbon` overrides the size down for list mode's much smaller thumbnail (`.prestaspot-list-item .prestaspot-card-image` is `4.5rem` wide vs. the grid's fluid, typically-much-larger square) - same technique, smaller numbers, not a different implementation.
- **Without image** (`show_image` off, or the product has none): the ribbon has nothing to wrap around, so `$prestaspot_render_badge(array $product): string` renders a plain `<span class="prestaspot-card-badge">Sale</span>` as the first child of the card/row wrapper instead - the flat badge from before the ribbon existed, now scoped to exactly this fallback case (`empty($product['on_sale']) || ($show_image && !empty($product['image_url']))` short-circuits to `''` otherwise, so the two never both render for the same product).

**Color** (`$sale_badge_color`, resolved in the Renderer like `button_color`) is applied inline, not in the stylesheet - `prestaspot.css` only supplies the ribbon/badge *shape* (position, rotation, padding, font) with no `background`/`color` declared, same division of responsibility as the shop link button. Both the ribbon and badge spans get the identical `style="background-color: ...; color: ...;"` attribute, built once per render as `$prestaspot_sale_badge_style` and passed into both closures - `color` comes from `Presta_Spot_Renderer::get_contrasting_text_color($sale_badge_color)`, the same static method the shop link button uses, so a badly-contrasting badge color can't happen any more than a badly-contrasting button can.

### Shop Link (`$prestaspot_render_link`)

Built once per render (not inside the element closure above), since its label/style/color don't vary by product:

```php
$prestaspot_link_label = '' !== $link_text ? $link_text : __('View in shop', 'prestaspot');
```

This is where the translated-default fallback described in the Renderer section actually applies - `$link_text` reaching the template as `''` means neither the instance nor the setting customized it.

When `$link_style === 'button'`, the closure adds the `prestaspot-card-link--button` class and an inline `style="background-color: ...; color: ...;"` - the background is the admin-configured `$button_color` directly, and the text color comes from `Presta_Spot_Renderer::get_contrasting_text_color($button_color)`, a static method (not tied to any instance) so the template can call it without holding a `Presta_Spot_Renderer` object. Its brightness formula (`(r*299 + g*587 + b*114) / 1000`, threshold 128) is carried over unchanged from `Dinky_Chat::get_contrasting_text_color()` in the reference project, which uses it for the same purpose (picking readable text over an admin-configured background color). Plain `link` style gets neither the class nor any inline style - it's styled purely via the static `.prestaspot-card-link` rule in `prestaspot.css`.

---

## Shortcode (`Presta_Spot_Shortcode`)

`[prestaspot]` → `display_products()`. All attributes default to a sentinel (`0` for numbers, `''` for strings/booleans, including `layout`, `price_position`, `sort`, `view_mode`, `link_text`, `link_style`, `button_color`, `sale_badge_color`) via `shortcode_atts()`; `show_image`/`show_name`/`show_description`/`show_price`/`show_stock_status` are only added to the renderer args array when the shortcode attribute wasn't the empty-string sentinel, so omitting them correctly falls through to the settings default rather than being coerced to `false`. `sort=""` (the default) is passed straight through like `layout`/`price_position` - the renderer's own sentinel handling resolves it, no special-casing needed here despite `''` being sort's *meaningful* default too (see the Renderer section).

`show_*` values are parsed by `parse_bool()`: anything except `no`/`false`/`0` (case-insensitive) is `true` — so `yes`, `1`, `true`, or simply omitting a recognizable "falsy" word all mean "shown". `on_sale` defaults to `'no'` (not the empty-string sentinel) and is always parsed via the same `parse_bool()` - unlike the `show_*` flags it has no settings-level default to fall through to, so there's no "unset" state to distinguish.

`category_name`/`language` (both default `''`) exist **only** on the shortcode, not the block attribute set - the block resolves straight to a numeric id via its own picker UI (see below), so it has no use for a name/code-based lookup at render time. Both are passed straight through to the renderer unchanged; resolution (and the `category_id`/`language_id`-wins precedence) happens there, not here.

---

## Gutenberg Block (`Presta_Spot_Block` + `blocks/product-list/`)

Registered via `register_block_type(PRESTASPOT_PLUGIN_DIR . 'blocks/product-list', ['render_callback' => [$this, 'render']])` on `init`. The `render_callback` argument is what makes this a **dynamic** block - `block.json` has no `render` field, and the block's `save()` returns `null` (nothing is serialized into post content except the attributes).

**No build tooling**: `index.js` is hand-written against the `window.wp.*` globals (`wp.blocks`, `wp.element`, `wp.blockEditor`, `wp.components`, `wp.serverSideRender`, `wp.i18n`, `wp.apiFetch`) using `element.createElement` directly - no JSX, no webpack, matching DinkyChat's own no-build philosophy for its frontend JS. Because there's no build step to auto-generate an `index.asset.php` (the way `@wordpress/scripts` normally would), `index.asset.php` is **hand-maintained** and must list every `wp-*` script handle the editor script actually uses, or WordPress will enqueue `index.js` without those dependencies loaded first and it will fail silently in the console.

### Category picker (REST route + `renderCategoryControl()`)

Unlike every other block control, "which category" can't be a fixed, hand-written options list - it depends on what categories the connected shop actually has. `Presta_Spot_Block` exposes a small internal REST route for this, registered via `register_rest_routes()` on `rest_api_init`:

```php
register_rest_route('prestaspot/v1', '/categories', array(
    'methods' => 'GET',
    'callback' => array($this, 'get_categories_route'),
    'permission_callback' => fn() => current_user_can('edit_posts'),
));
```

`get_categories_route()` just wraps `$this->api->get_categories()` in a `WP_REST_Response` - all the actual fetching/caching/fallback logic lives in `Presta_Spot_Api` (see above), this route is a thin authenticated proxy so the block editor's JS (which can't hold a Webservice API key or call PrestaShop directly) can get at it. Gated behind `edit_posts` rather than left public - it's internal editor-support data, not something the site's public REST API surface needs to expose.

`index.js`'s `edit()` calls it on mount:

```js
const [ categories, setCategories ] = useState( null ); // null = loading
useEffect( function () {
    apiFetch( { path: '/prestaspot/v1/categories' } )
        .then( setCategories )
        .catch( function () { setCategories( [] ); } );
}, [] );
```

`renderCategoryControl(categories, categoryId, onChange)` then builds a `SelectControl` with an `"All Categories"` (`value: '0'`) option plus one per fetched category (`value` = category id as a string, `label` = its name) - swapped in for the plain numeric "Category ID" `TextControl` only once `categories && categories.length > 0`; while loading (`null`) or after a failed/empty fetch (`[]`), the numeric field is what renders instead, so the block never becomes unusable over a PrestaShop-side hiccup. One extra wrinkle: if the block's stored `categoryId` isn't among the fetched categories (an inactive/deleted category, or the block was set up before the shop had this category), a synthetic `"Category #{id} (not in list)"` option is appended so that stored value stays visibly selected instead of the control silently looking like it reset to "All Categories" - it hasn't actually changed, `categoryId` is untouched until the user makes a different choice.

The shortcode has no equivalent client-side control to feed, which is exactly why `category_name` (resolved server-side, see the Shortcode section) exists as its separate, parallel way to select a category by name.

### Language picker (REST route + `renderLanguageControl()`)

Same shape as the category picker immediately above, one `/prestaspot/v1/languages` route (`get_languages_route()` wrapping `$this->api->get_languages()`) and one `useEffect`/`useState` fetch feeding `renderLanguageControl(languages, languageId, onChange)` - the only real difference is the sentinel option's label: `"Automatic (page language)"` (`value: '0'`) instead of `"All Categories"`, since `languageId: 0` means "let Polylang/WPML (or the first shop language) decide" rather than "no filter". Same graceful-degradation and "not in fetched list" synthetic-option handling as categories; same reason the shortcode has its own separate `language`/`language_id` server-side resolution path (see the Shortcode section) instead of sharing this client-side control.

Editor preview uses `<ServerSideRender block="prestaspot/product-list" attributes={...} />`, which calls the same `render_callback` (via the REST API) that produces the frontend output - so the editor and frontend can never visually diverge.

**Attributes** → renderer args mapping (camelCase in the block, snake_case internally): `productCount`→`product_count`, `categoryId`→`category_id`, `onSale`→`on_sale`, `sort`, `columns`, `showImage`→`show_image`, `showName`→`show_name`, `showDescription`→`show_description`, `showPrice`→`show_price`, `showStockStatus`→`show_stock_status`, `pricePosition`→`price_position`, `layout`, `viewMode`→`view_mode`, `linkText`→`link_text`, `linkStyle`→`link_style`, `buttonColor`→`button_color`, `saleBadgeColor`→`sale_badge_color`. Unlike the shortcode, block attributes always have concrete values (block.json `default`s) - there's no "unset" state once a block is inserted, so a block instance doesn't dynamically track later changes to the global settings default; it's a snapshot taken at insertion time, same as any other Gutenberg block attribute. `linkText` is the one exception worth noting: its block.json default is `""` (not a hardcoded "View in shop"), specifically so a freshly inserted block still resolves to the *translated* built-in label via the template, rather than baking English text into every new block. `onSale` defaults to `false`, matching `categoryId`'s `0` default - both are "no filter" defaults with no corresponding global setting. `sort`'s block.json default is `""` too, but unlike those two it *does* have a corresponding global setting (`SORT_DEFAULT` is also `""`) - the block's default and the setting's default just happen to coincide, the same way `linkStyle`'s block default (`"link"`) coincides with `LINK_STYLE`'s.

**Element-order pickers are shared code**: `renderElementOrderPicker(options, selectedValue, radioGroupName, onChange)` in `index.js` is the generic version of what was, before 0.10.0, a `renderLayoutPicker()` hardcoded to the `layout` attribute - it now backs both the Card Layout picker (`LAYOUT_OPTIONS`) and the Price Position picker (`PRICE_POSITION_OPTIONS`), since both are "pick one arrangement, preview it as a row of `.prestaspot-layout-block--*` bars" pickers with nothing else attribute-specific in the markup. A new picker of this same shape (options array with `value`/`order`/`label`) only needs a new `*_OPTIONS` array, not a new render function.

**Visual pickers**: both `templates/settings-page.php` and `blocks/product-list/index.js` render three radio-based pickers sharing the same `.prestaspot-layout-picker`/`.prestaspot-layout-option` shell (a styled `<label>` wrapping a real radio input, so keyboard/label-click semantics come for free) but different preview content:

- **Card Layout** (element order): `.prestaspot-layout-preview` with `.prestaspot-layout-block--image/name/description` bars, ordered per `LAYOUT_ELEMENT_ORDER`.
- **Display Mode** (`view_mode`): `.prestaspot-viewmode-preview` with a small 2×2 grid of squares (`--grid`) or three stacked bars (`--list`) - see `VIEW_MODE_OPTIONS` in `index.js` and the parallel markup in `settings-page.php`.
- **Shop Link Style** (`link_style`): `.prestaspot-linkstyle-preview` with an underlined bar (`--link`) or a filled swatch (`--button`) - the button swatch's background is set inline to the *actual currently-configured* `button_color`, not a fixed preview color, so the picker doubles as a live color check.

Selected state is pure CSS (`:has(input:checked)`), no JS needed for the visual state. All three pickers share `assets/css/layout-picker.css` (the name predates the later pickers but wasn't worth renaming/splitting for a few more small rulesets) - enqueued directly by `Presta_Spot_Admin::enqueue_admin_scripts()` for the settings page, and via `block.json`'s `editorStyle` field for the block editor. In the block editor, each picker's radio `name` is scoped with the block's `clientId` (e.g. `'prestaspot-layout-' + props.clientId`) so multiple block instances on one page can't cross-uncheck each other via native radio-group semantics - defensive, since Gutenberg only mounts one block's `InspectorControls` in the sidebar at a time in practice.

**Button color control**: unlike the other pickers, the actual color value is set via `wp.blockEditor.PanelColorSettings` (the same component core blocks like Paragraph/Button use for their color settings), not a custom control - unlike a 2-3 option enum, "pick any color" isn't a good fit for the visual-card-picker pattern. It's rendered as its own panel, conditionally (`'button' === attributes.linkStyle && el(PanelColorSettings, {...})`) - only shown once Link Style is set to Button. The settings page (plain PHP form, no reactive show/hide) instead always shows the native `<input type="color">` field with a "only used when Shop Link Style is Button" description, matching how `columns`/`view_mode` handle the same kind of conditional relevance.

**Sale badge color control** follows the identical `PanelColorSettings` pattern, but *unconditionally* - there's no other attribute that gates whether the sale indicator can appear (it's driven by each product's own `on_sale` flag, not a setting), so its panel is always rendered rather than behind a `condition && el(...)` check like the button color panel.

**Sort control**: deliberately *not* a visual picker like the others - `SORT_OPTIONS` (7 named, mutually exclusive choices: default, name/price ascending/descending, date newest/oldest) backs a plain `wp.components.SelectControl` in the block and a plain `<select>` on the settings page. A visual picker earns its keep when there's something meaningful to preview (element order, a color swatch); "Price (Low to High)" has no useful visual shorthand, so a picker here would just be a slower, larger dropdown - the standard native control is the better fit.

---

## Admin Settings Page (`Presta_Spot_Admin` + `templates/settings-page.php`)

Plain WordPress Settings API - the form POSTs to `options.php`, `settings_fields('prestaspot_settings_group')` handles the nonce, each option is `register_setting()`-ed with its own `sanitize_callback`. No custom AJAX handler, no custom save logic. DinkyChat (the reference project) uses a custom AJAX-based settings save instead, for UX polish its larger scope justifies - PrestaSpot doesn't need that yet; a plain form submit is simpler and sufficient for a handful of fields.

**Checkbox persistence gotcha**: an unchecked HTML checkbox submits no value at all, so `options.php` would silently keep the *old* stored value on uncheck instead of saving `false`. Each boolean field therefore has a same-named `<input type="hidden" value="0">` immediately before the checkbox in the DOM - browsers submit both when checked (`0` then `1`; PHP keeps the *last* value for a repeated field name, i.e. `1`) and only the hidden `0` when unchecked. Any new boolean settings field must follow this pattern or unchecking it will silently do nothing.

### Per-Field Reset Buttons (`assets/js/settings-page.js`)

Every field on the settings page except `shop_url`/`api_key` (resetting those to `''` would just wipe the shop connection, not "restore a preference" - not what this is for) gets a small reset icon next to its label, visible only once that field's live value has drifted from the plugin's default - not a static "always there" button. `templates/settings-page.php`'s local closure `$prestaspot_reset_button(string $target_name, string $default_value): string` renders it: a `<button class="prestaspot-field-reset" data-target="..." data-default="...">`, `data-target` matching the field's `name` attribute (not `id` - this is what lets one button address an entire radio *group* by name, not just a single input) and `data-default` carrying that field's `PRESTASPOT_SETTINGS_DEFAULTS` entry as a string (`'1'`/`'0'` for booleans).

All the actual behavior is in `assets/js/settings-page.js` (enqueued only on the settings page, alongside a matching `assets/css/settings-page.css` for the icon's shape/visibility toggle - no color is hardcoded there since `.is-visible` is the only state that matters). For each button, on load and on every `input`/`change` of its target field(s): compare the field's current value against `data-default` and toggle `.is-visible` accordingly. Three value shapes are handled uniformly by `getFields()`/`currentValue()`/`applyDefault()`:

- **Plain input** (text/number/color): `.value` compared/set directly as a string.
- **Checkbox**: `.checked` mapped to/from `'1'`/`'0'`. `getFields()` explicitly filters out `type === 'hidden'` - `document.getElementsByName()` on a checkbox's name also returns its hidden companion input from the persistence gotcha above, which would otherwise be mistaken for "the field" (a hidden input's `.value` is always `'0'`, so comparisons would be permanently wrong without this filter).
- **Radio group**: several inputs share one `name`; the checked one's `.value` is the current value, and reset checks whichever radio's `.value` equals `data-default`.

No new build tooling, no dependency on the block editor's JS - this is a separate, much smaller vanilla script scoped to the admin settings page only, following the same "hand-written against the DOM, no framework" approach as `blocks/product-list/index.js` (though for a plain PHP admin page rather than a React-based block, there's no shared code between the two - they solve different problems).

### Theme Color Palette (`assets/js/settings-color-picker.js`)

`button_color` and `sale_badge_color` used a plain `<input type="color">` - the browser's own picker, with no idea what colors the site's actual design uses, unlike the block sidebar's `PanelColorSettings`, which shows the active theme's `theme.json` palette as swatches. Found via a user report that the settings page and the block behaved inconsistently here.

Fixed by mounting `wp.components.ColorPalette` (the same component `PanelColorSettings` uses internally) over each color field, unlike the reset buttons this needed real block-editor packages (`wp-element`, `wp-components` script + style), since there's no plain-DOM equivalent of a palette-aware color picker. `Presta_Spot_Admin::get_theme_color_palette()` resolves the palette PHP-side via `wp_get_global_settings(['color', 'palette'])` and passes it to the script through `wp_localize_script()` - preferring the theme's own palette over WordPress's built-in default one (empirically what the block editor itself shows for a theme with a `theme.json` palette), with any user-customized palette appended on top.

**Not every registered palette color is a literal color, confirmed against two real themes.** `button_color`/`sale_badge_color` are stored and validated as plain hex (`sanitize_hex_color()`), since `get_contrasting_text_color()` needs a real RGB value to pick readable button text. Twenty Twenty-Five's "Accent 6" is `color-mix(in srgb, currentColor 20%, transparent)` - a CSS function with nothing to extract, dropped outright. Blocksy's entire palette is `var(--theme-palette-color-1, #2872fa)`-style - a CSS custom property, but with a hex *fallback* baked in that tracks the theme's actual configured color; `resolve_hex_color()` extracts and uses that instead of dropping it, which is what made Blocksy's swatches show up as empty/unusable before this was added. Anything that's neither a literal hex nor a `var(..., #hex)` is dropped rather than shown as a swatch that would silently fail to save.

**`ColorPalette`'s current-color trigger button has no built-in max-width** - it's designed to live in a ~260px block editor sidebar panel and just stretches to fill its container, which on the settings page's wide table cell rendered an ~833px-wide, 64px-tall bar (measured via `getBoundingClientRect()`, not caught by earlier testing since that only checked DOM/ARIA state, never actual rendered layout). `.prestaspot-color-palette { max-width: 260px }` in `settings-page.css` fixes this.

The underlying `<input type="text">` (no longer `type="color"` - a bare hex string now, validated server-side by the existing `sanitize_hex_color()` callbacks) stays exactly where it was in the DOM and keeps its `name`, so `options.php` submission and `settings-page.js`'s reset-button logic (`getFields()`/`currentValue()`/`applyDefault()`) don't need to know a picker exists - `settings-color-picker.js` only adds a `.prestaspot-color-input--enhanced` class (CSS `display: none`) once it has successfully mounted its own picker next to it, so a JS failure leaves the native color input visible and fully functional instead of blank. The mounted `ColorPalette` and the input stay in sync in both directions purely through the `change` event: picking a swatch sets `input.value` and dispatches `change`; the reset button doesn't touch the picker directly, it only resets `input.value` - so `applyDefault()` was changed to dispatch `change` on every reset (previously it didn't fire any event) purely so the color picker's own `change` listener notices.

---

## Adding a New Setting/Attribute (quick reference)

See `.doc/DEVELOPER_GUIDE.md` for the full worked example. In short, touch: `Presta_Spot_Settings` (constant + default + sanitization in `get_all()`), `Presta_Spot_Admin::register_settings()` (+ `templates/settings-page.php` field), `Presta_Spot_Renderer::render()` (merge logic - pick the right sentinel convention above), `Presta_Spot_Shortcode::display_products()` (shortcode attribute), `Presta_Spot_Block::render()` + `blocks/product-list/block.json` + `index.js` (block attribute + control).

---

**End of Technical Architecture Documentation**
