# PrestaSpot - Testing

An automated test suite, modeled directly on DinkyChat's (a sibling WordPress plugin project by the same author) three-tier setup - reused wherever it fit, adapted where PrestaSpot's shape genuinely differs. Implemented; see `.doc/DEVELOPER_GUIDE.md`'s "Running the tests" section for how to actually run each tier. What follows is the reasoning behind the shape of the suite, not just its layout.

---

## Why base this on DinkyChat

DinkyChat already solved the two hard, WordPress-plugin-specific problems any suite here would also hit:

- **Unit tests can't load WordPress** (no `wp-load.php`, no DB) but plugin files all start with `if (!defined('ABSPATH')) exit;` - DinkyChat's `bootstrap-unit.php` defines a dummy `ABSPATH` and loads only the specific class file under test, nothing else.
- **Integration tests need a real, disposable WordPress** - DinkyChat downloads WP core's own PHPUnit test library (`bin/install-wp-tests.sh`) and runs against `WP_UnitTestCase` inside the dev Docker container, no mocking of `$wpdb`/hooks/`wp_die()`.

Both of those are copy-paste-and-adjust-container-names reusable as-is. What's *not* reusable as-is is DinkyChat's actual Integration-test *content* - it exercises its own custom DB tables (`dinky_sessions`, `dinky_messages`); PrestaSpot has none. PrestaSpot's equivalent "does this actually work against something real" concern is its dependency on PrestaShop's Webservice API - see below.

---

## The one real architectural difference: an external system

DinkyChat's Integration tests only ever talk to WordPress's own database - fully self-contained, no network, no other service has to be running. PrestaSpot's core logic (`Presta_Spot_Api`) instead makes real HTTP calls to a *separate* system (PrestaShop) via `wp_remote_get()`. That changes the honest options for "integration-level" testing:

1. **Mock the HTTP layer** (`pre_http_request` filter returning canned JSON), or
2. **Hit a real, disposable PrestaShop instance** - exactly the `prestashop` service already in `prestaspot-docker/docker-compose.yml`, with fixture data already documented in `DEVELOPER_GUIDE.md` (the Hummingbird t-shirt, the persisted "Sale Test" page, product 3's zeroed stock, the German-translated product 1, etc.).

**Recommendation: option 2, for this project specifically.** Not a generic preference - a conclusion from this project's own track record. Every serious bug found so far (`products.quantity` silently always returning `0`, the untranslated-field 500 on multilingual shops, `on_sale` being shop-scoped not product-scoped, Blocksy's `var(...)` palette colors, the `/api/api/...` double-slash from a trailing `/api` in the Shop URL field) was caught specifically by testing against PrestaShop's *actual* behavior, not by predicting it. A mocked `wp_remote_get()` response is exactly as correct as whoever wrote the mock's mental model of the webservice - which is the thing that kept turning out to be wrong. Mocked HTTP tests would be faster and wouldn't need Docker running, but they'd verify PrestaSpot's code against our own assumptions instead of PrestaShop's real API, which is precisely the gap that's bitten this project every time so far.

Trade-off to accept knowingly: these Integration tests are not hermetic. They need the `prestaspot-docker` stack up, and they're only as stable as the fixture data documented in `DEVELOPER_GUIDE.md` - if someone edits "Hummingbird printed t-shirt"'s price by hand in the back office, a test asserting `23.90 €` breaks, correctly. That's an accepted cost, not an oversight.

---

## Structure

```
prestaspot/                       (repo root, unchanged)
├── composer.json                 # require-dev only - phpunit, phpcs/wpcs; not shipped in the plugin zip
├── package.json                  # Playwright, dev-only
├── phpunit.xml.dist              # Unit suite
├── phpunit-integration.xml.dist  # Integration suite
├── playwright.config.ts          # E2E suite
├── bin/
│   └── install-wp-tests.sh       # ~verbatim from DinkyChat, WP_TESTS_DOMAIN/TITLE strings updated
├── .docker/
│   └── test.sh                   # docker exec wrapper for the Integration suite
└── tests/
    ├── bootstrap-unit.php
    ├── bootstrap-integration.php
    ├── Unit/
    │   ├── ContrastColorTest.php         # Presta_Spot_Renderer::get_contrasting_text_color()
    │   ├── LayoutElementOrderTest.php    # Presta_Spot_Settings::get_layout_element_order()
    │   ├── SortArgsTest.php              # Presta_Spot_Api::build_sort_args()
    │   ├── FormatPriceTest.php           # Presta_Spot_Api::format_price()
    │   └── LocalizedValueTest.php        # Presta_Spot_Api::localized_value()
    ├── Integration/
    │   ├── IntegrationTestCase.php       # same expectDeprecated() workaround DinkyChat needed
    │   ├── ProductFetchTest.php          # Presta_Spot_Api::get_products() against the real shop
    │   ├── CategoryAndLanguageTest.php   # get_categories()/get_languages() + name/code resolution
    │   ├── StockStatusTest.php           # product 3's zeroed stock (see .doc/DEVELOPER_GUIDE.md)
    │   └── RendererTest.php              # Presta_Spot_Renderer::render() - sentinel/fallback logic
    └── e2e/
        ├── global-setup.ts       # clear _transient_prestaspot_* before the run (same idea as
        │                         # DEVELOPER_GUIDE.md's manual pre-test-cache-clear step, automated)
        ├── clear-cache.php       # the actual clearing, shelled into the container by global-setup.ts
        ├── helpers/
        │   └── login.ts          # shared wp-login.php helper
        ├── settings-page.spec.ts
        ├── block-editor.spec.ts
        └── shortcode-frontend.spec.ts
```

Everything here (`composer.json`, `package.json`, `bin/`, `.docker/`, `tests/`) is dev tooling, not part of the shipped plugin - same framing DinkyChat's own `composer.json` description line uses ("Test tooling ... Not part of the shipped plugin package"). `build-package.php`/`build.xml` already zip only `prestaspot/` (per `ARCHITECTURE.md`), so this needs no changes there.

---

## Unit tests (`tests/Unit/`)

Honest caveat up front: PrestaSpot's pure-PHP-no-WordPress-functions surface is small - most methods call `absint()`, `sanitize_hex_color()`, `get_transient()`, `wp_remote_get()`, etc., which don't exist without WordPress loaded. This mirrors DinkyChat's own constraint (their one Unit test also happens to be a pure `static` method with zero WP calls).

**Testable as-is:**
- `Presta_Spot_Renderer::get_contrasting_text_color()` - public, static, pure (`ltrim`/`hexdec`/`substr` only). Same method DinkyChat's own `ContrastColorTest.php` covers (this project's version was carried over from there) - the test file mirrors it, retargeted to PrestaSpot's actual default colors (`#2271b1` button, `#e63946` sale badge).
- `Presta_Spot_Settings::get_layout_element_order()` - public, static, pure (class-constant array lookups only).

**Promoted from `private` to `public static` for testability** (pure, no `$this`, no external side effects, nothing depended on them staying private - see "Decisions made" below):
- `Presta_Spot_Api::build_sort_args()` - sort string → PrestaShop `sort`/`date` query args.
- `Presta_Spot_Api::localized_value()` - unwraps a multilingual `{id, value}[]` webservice field to a plain string.
- `Presta_Spot_Api::format_price()` - amount + currency shape → display string.

These three were exactly the kind of "given this input, is the output right" logic previously *only* exercised indirectly, deep inside `fetch_products()`. Call sites inside `Presta_Spot_Api` itself now use `self::` instead of `$this->`.

Everything else (shortcode attribute parsing, block attribute mapping, the renderer's sentinel-resolution logic) either calls WordPress functions directly or is tightly coupled to `Presta_Spot_Api`/`Presta_Spot_Settings` - covered at the Integration level instead, same reasoning DinkyChat applied to its own AJAX handlers.

---

## Integration tests (`tests/Integration/`)

Real `WP_UnitTestCase`, real disposable WP test DB (via `bin/install-wp-tests.sh`, same as DinkyChat), running inside the `prestaspot-docker-wordpress-1` container - **plus** the `prestaspot-docker-prestashop-1` service has to be up, with the same Webservice API key and fixture data `DEVELOPER_GUIDE.md` documents for manual testing. `bootstrap-integration.php` seeds `prestaspot_shop_url`/`prestaspot_api_key` (pointing at `host.docker.internal:8093`, overridable via `PRESTASPOT_TEST_SHOP_URL`/`PRESTASPOT_TEST_API_KEY` env vars) before any test runs, since `Presta_Spot_Api::get_products()` returns `[]` outright without them.

Coverage, grounded in fixtures that already exist:

- **`ProductFetchTest`** - `get_products()` against category 4 ("Men", containing only the Hummingbird t-shirt) returns exactly one product with the expected name/price/`on_sale`/`stock_status`; `language_override_id` against the German-translated product 1 returns "Kolibri-bedrucktes T-Shirt"; `language_override_id: 1` returns English regardless of what the site's own language happens to be; missing shop credentials return `[]` rather than erroring.
- **`CategoryAndLanguageTest`** - `get_categories()` includes the known category names; `resolve_category_id_by_name()` case-insensitive match + graceful `0` on no match; `get_languages()` returns `en`/`de`; `resolve_language_id_by_code()` matches and falls through correctly.
- **`StockStatusTest`** - product 3 (persisted at `quantity=0`) reports `out_of_stock`, products 1/2 report `in_stock`. Deliberately doesn't cover the "shop doesn't track stock at all" fallback (`PS_STOCK_MANAGEMENT` off) - see the test file's own docblock for why toggling that isn't something this suite automates.
- **`RendererTest`** - the `!empty()` vs. `array_key_exists()` sentinel split documented in `ARCHITECTURE.md` (an explicit boolean `false` for `show_price` must not fall back to the setting default; an unset key must), plus `category_name` resolving identically to the equivalent `category_id` and falling through to "no filter" on no match rather than erroring.

---

## E2E tests (`tests/e2e/`)

Playwright, TypeScript, against the live dev stack - now reachable at **`http://prestaspot.localhost`** now that the Traefik `dev-proxy` label is set up (see recent `docker-compose.yml` change), matching DinkyChat's `playwright.config.ts` comment almost word-for-word: there's no clean "build and serve" step for a WordPress plugin, so this runs against the same stack every fix in this project has already been manually verified against, not a Playwright-managed server.

```ts
// playwright.config.ts
use: {
    baseURL: 'http://prestaspot.localhost',
    trace: 'retain-on-failure',
},
```

Specs:

- **`settings-page.spec.ts`** - log in, change Shop Link Button Color via the theme `ColorPalette`, verify persistence after reload and that the reset button correctly reverts it (this exact flow needed three iterations to get right this session - a very direct return on the test-writing investment). Each test starts by resetting the color to its default, since the setting is persistent `wp_options` state shared across runs - see the "swatch" selector note below for a real bug this caught.
- **`block-editor.spec.ts`** - inserts the "PrestaShop Product List" block via `wp.data`/`wp.blocks` directly (not UI clicking - see the file's own docblock), confirms the category/language `SelectControl`s populate from the real REST routes with the fixture category name visible (not the numeric-field fallback), confirms the frontend renders the configured product.
- **`shortcode-frontend.spec.ts`** - `[prestaspot category_id="4" language="de"]` on a throwaway page renders the German product name, price, and "In Stock" label.

**A real bug this caught while writing it**: the first `settings-page.spec.ts` swatch-selector (`getByRole('button').filter({ hasNotText: 'Custom' })`) matched the palette's "Custom color" trigger button too - its own "Custom" label is a *sibling* `<span>`, not inside the `<button>`, so filtering by visible text didn't exclude it. The fix targets the trigger's distinguishing DOM class (`.components-color-palette__custom-color-button`) directly instead. Worth knowing if this component is touched again - it's an easy trap for any future spec too.

**Fixture data / cleanup:** the shop-side fixtures (Hummingbird t-shirt in category 4, its German translation) are read-only as planned. But `block-editor.spec.ts`/`shortcode-frontend.spec.ts` ended up each creating their own throwaway WordPress page per test (via `wp.apiFetch`) rather than depending on the persisted dev-stack fixture pages `DEVELOPER_GUIDE.md` documents - those pages' exact shortcode attributes aren't something this suite owns or should assume stay unchanged, and a page created and deleted within one test is simpler to reason about than "does fixture page X still have the attributes a test from months ago expected." Each spec deletes its page in `afterEach` (not a `global-teardown.ts` - no cross-test WordPress content accumulates that needs a final sweep, unlike DinkyChat's per-spec `e2e_*` users). One thing worth knowing if a test is interrupted mid-run (Ctrl+C, a crash) before `afterEach` fires: its throwaway page is orphaned and needs a manual `wp_posts` cleanup - not automated, since a stuck run is already an abnormal state worth looking at by hand rather than silently swept away by a teardown.

`global-setup.ts` clears `_transient_prestaspot_*` before the run, exactly the manual step `DEVELOPER_GUIDE.md` already tells a human to do before every live re-test.

**A concurrency lesson from actually running this**: Playwright's default `workers` setting runs multiple spec *files* in parallel even with `fullyParallel: false` (which only serializes tests *within* one file) - against a single shared, not-especially-beefy dev-stack container, that caused intermittent login timeouts under real load. `playwright.config.ts` pins `workers: 1`. Slower, but this suite trades speed for not being flaky against shared infrastructure it doesn't own.

---

## What's directly reusable from DinkyChat vs. what needs rework

| Piece | Reuse |
|---|---|
| `bin/install-wp-tests.sh` | Copy verbatim, change nothing but the default `WP_TESTS_DOMAIN`/`WP_TESTS_TITLE` strings |
| `bootstrap-unit.php` pattern (dummy `ABSPATH`, load one file) | Copy the pattern, PrestaSpot-specific file paths |
| `bootstrap-integration.php` pattern (`tests_add_filter('muplugins_loaded', ...)` + real `activate()`) | Copy the pattern; PrestaSpot's `activate()` is simpler (no custom-table migration to worry about triggering early) |
| `IntegrationTestCase`'s `expectDeprecated()` workaround | Copy verbatim - it's a WP-core/PHPUnit-11 compatibility gap, not DinkyChat-specific |
| `.docker/test.sh` | Copy the pattern, swap the container name |
| `composer.json`/`package.json` shape, `phpcs`+`wpcs` for linting | Copy the shape, PrestaSpot-specific `require-dev` versions |
| `playwright.config.ts`'s "no build step, test the live stack" approach | Copy the reasoning and the `baseURL` pattern |
| DinkyChat's actual Integration *test content* (custom-table CRUD) | **Not reusable** - no custom tables here; replaced by the live-PrestaShop approach above |
| DinkyChat's E2E fixture creation/cleanup (`e2e_*` users) | **Doesn't map 1:1** - see "Fixture data / cleanup" above; PrestaSpot's specs create/delete their own throwaway pages per test instead of a per-suite user prefix |

---

## Decisions made

1. **`build_sort_args()`/`localized_value()`/`format_price()` promoted to `public static`.** All three are pure (no `$this`, no WP function calls) and were only ever exercised indirectly, deep inside `fetch_products()` - low risk, and it's exactly the `get_contrasting_text_color()` precedent this codebase already established for the same reason. Call sites updated to `self::` accordingly.
2. **CI skipped**, per explicit instruction - this suite is local-only for now, same as the project's manual-testing workflow has been throughout.
3. **All three tiers built in one pass** - Unit (5 test files, 20 tests), Integration (4 test files, 15 tests, run against the real dev-stack PrestaShop), E2E (3 specs, 5 tests, Playwright against `http://prestaspot.localhost`). All passing as of this writing.
