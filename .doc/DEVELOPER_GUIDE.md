# PrestaSpot - Developer Guide

How to work on PrestaSpot: local test environment, coding conventions, and a worked example for the most common kind of change (adding a new display option).

---

## Local Test Environment (Docker)

PrestaSpot needs both a WordPress **and** a PrestaShop instance to test against, isolated from any other Docker-based projects on the same machine. The stack lives outside the plugin repo, alongside sibling WordPress dev stacks:

```
Z:\dev\wordpress\prestaspot-docker\docker-compose.yml
```

Four services: `wordpress` (port **8092**), `wp-db`, `prestashop` (port **8093**), `ps-db`. The plugin folder is bind-mounted straight into the WordPress container, so edits under `prestaspot/` are picked up immediately, no rebuild/restart needed:

```yaml
volumes:
  - wp_data:/var/www/html
  - P:/dev/prestaspot/prestaspot:/var/www/html/wp-content/plugins/prestaspot
```

Start/stop from that directory:

```bash
docker compose up -d
docker compose down        # add -v to also drop the volumes (fresh WP + PrestaShop install)
```

### The `host.docker.internal` domain trick

This is the one non-obvious piece of the setup, worth understanding before touching it: PrestaShop redirects (302) any request whose `Host` header doesn't match its configured shop domain. That domain must therefore be reachable under the **exact same hostname:port** from two different places that normally can't share one:

- **the WordPress container**, doing server-side `wp_remote_get()` calls to the PrestaShop Webservice API (needs a Docker-internal-reachable address)
- **the visitor's browser**, loading `<img>` tags and "View in shop" links straight out of rendered HTML (needs a host-reachable address)

`host.docker.internal` resolves to the same address from both contexts on Docker Desktop for Windows (it adds the entry to the Windows hosts file too, not just inside containers) - so the compose file sets `PS_DOMAIN: host.docker.internal:8093`, and the PrestaSpot settings page's "Shop URL" is set to `http://host.docker.internal:8093` accordingly. Plain `localhost:8093` does **not** work for the WordPress-container side (its own `localhost` is itself, not the host machine) - don't "simplify" this back to `localhost`.

If PrestaShop was ever installed with a different `PS_DOMAIN` (e.g. after a `docker compose down -v` without updating the compose file first, or after changing the port mapping - see below), fix it directly in the DB rather than reinstalling:

```bash
docker exec prestaspot-docker-ps-db-1 mariadb -uprestashop -pprestashop prestashop -e \
  "UPDATE ps_shop_url SET domain='host.docker.internal:8093', domain_ssl='host.docker.internal:8093' WHERE id_shop=1;
   UPDATE ps_configuration SET value='localhost:8093' WHERE name IN ('PS_SHOP_DOMAIN','PS_SHOP_DOMAIN_SSL');"
```

### Changing the host ports

Both host ports (`8092`/`8093`) were picked to avoid clashing with sibling dev stacks (`dinkychat-docker` on `8080`, `voteflowmanager-docker` on `8081`) - check `docker ps -a` (or `docker inspect <container> --format '{{json .HostConfig.PortBindings}}'` for containers that aren't currently running) across *all* Docker projects on the machine before picking new ones, not just this repo; a silent port clash with an unrelated container can leave one of the two just not actually bound (`docker ps` still shows it as "Up") without Docker raising any error. If the ports ever need to change again, four places must be updated together, not just `docker-compose.yml`:

1. `docker-compose.yml` - both `ports:` mappings and the `PS_DOMAIN` env var.
2. WordPress's own `siteurl`/`home` options (`wp_options`) - not derived automatically, baked in at install time.
3. PrestaShop's `ps_shop_url.domain`/`domain_ssl` and `ps_configuration`'s `PS_SHOP_DOMAIN`/`PS_SHOP_DOMAIN_SSL` (see above) - all four, not just `ps_shop_url`, are consulted for redirect/canonical-URL decisions.
4. PrestaSpot's own `prestaspot_shop_url` option, and its cached `_transient_prestaspot_*` rows (safe to just delete, they'll repopulate on the next request).

Then recreate the two containers (`docker compose up -d --force-recreate wordpress prestashop`) - a plain `restart` reuses the already-established (now-stale) port binding rather than rebinding to the new one.

### Credentials (this dev stack only - not real secrets)

- **WordPress admin**: `admin` / `Admin1234!secure`
- **PrestaShop back office**: `admin@example.com` / `Admin1234!`, at `/admin<random-suffix>/` — PrestaShop's auto-installer renames the admin folder to a random slug on every install regardless of `PS_FOLDER_ADMIN`; check `docker logs prestaspot-docker-prestashop-1` for the line `You can now access your backoffice at ...` to get the current path.

### Enabling the Webservice API + getting a key (manual, after any fresh install)

PrestaShop's webservice is off by default and there's no env var to auto-enable it:

1. Back office → **Advanced Parameters → Webservice** → set "Enable PrestaShop's webservice" to **Yes** → Save.
2. **Add new webservice key** → click **Generate** for the key → give it a description → set "Enable webservice key" to **Yes**.
3. In the permissions table, tick the **View (GET)** column for the `products`, `images`, `currencies`, `categories`, `configurations`, `stock_availables`, and `languages` rows → Save. `languages` isn't just for testing Polylang/WPML sync (see below) - `get_products()` also needs it to avoid a 500 on sort-by-name against a shop with more than one configured language (see ARCHITECTURE.md's "Unscoped-request fallback"). Without `categories`, the block's category picker silently falls back to the old plain numeric field - fine for spot-checking other features, but worth remembering if that fallback shows up unexpectedly during a test. Without `configurations`/`stock_availables`, stock status just never shows, same graceful-degradation story.
4. Paste the generated key into PrestaSpot's settings page (`http://localhost:8092/wp-admin/admin.php?page=prestaspot-settings`) alongside the shop URL.

The demo shop comes pre-seeded with ~19 fixture products across a few categories (`Men`, `Women`, `Art`, `Stationery`, `Home Accessories`, plus the structural `Root`/`Home` categories every PrestaShop install has), which is enough to exercise `product_count`, `category_id`/`category_name`, and pagination-adjacent behavior without adding real data. Not every category has products assigned, though (`Accessories`/`Clothes` are empty on a fresh install) - worth checking `filter[id_category_default]` product counts directly against the webservice before assuming a "no products" result is a bug rather than just an empty category.

### Testing sort, and the multilingual unscoped-request fallback

`sort=name_asc`/`name_desc` exercises the exact code path that surfaced the "Unscoped-request fallback" bug fix in `Presta_Spot_Api::get_products()` (see ARCHITECTURE.md) - a quick regression check for that fix: with this dev shop's German language fixture in place (see below) and Polylang active and resolving a language, `[prestaspot sort="name_asc"]` should just work. To actually exercise the *fallback* path (no plugin-resolved language), temporarily deactivate Polylang and clear the transient cache:

```bash
docker exec prestaspot-docker-wp-db-1 mysql -uwordpress -pwordpress wordpress -e "
UPDATE wp_options SET option_value = 'a:1:{i:0;s:25:\"prestaspot/prestaspot.php\";}' WHERE option_name='active_plugins';
DELETE FROM wp_options WHERE option_name LIKE '_transient_prestaspot_%';
"
```

Products should still render correctly (names, prices, sorted) - if this instead shows "No products to display", the fallback is broken. Reactivate Polylang afterward the same way, with `polylang/polylang.php` added back to the serialized array, and clear the transient cache again.

### Testing the on-sale filter

None of the demo shop's fixture products are flagged `on_sale` by default, so `filter[on_sale]=1` legitimately returns nothing until at least one product has that flag set. There's no admin-UI-friendly bulk way to do this for a demo shop, and PrestaShop's `on_sale` field is a merchant-set flag independent of whether a product actually has a specific-price reduction (see `.doc/ARCHITECTURE.md`) - so a realistic test fixture needs both, set directly in the DB:

```bash
docker exec prestaspot-docker-ps-db-1 mysql -uprestashop -pprestashop prestashop -e "
UPDATE ps_product SET on_sale=1 WHERE id_product IN (1,2);
UPDATE ps_product_shop SET on_sale=1 WHERE id_product IN (1,2);
INSERT INTO ps_specific_price (id_specific_price_rule, id_cart, id_product, id_shop, id_shop_group, id_currency, id_country, id_group, id_customer, id_product_attribute, price, from_quantity, reduction, reduction_tax, reduction_type, \`from\`, \`to\`)
VALUES
(0, 0, 1, 1, 0, 0, 0, 0, 0, 0, -1, 1, 0.20, 1, 'percentage', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(0, 0, 2, 1, 0, 0, 0, 0, 0, 0, -1, 1, 0.15, 1, 'percentage', '0000-00-00 00:00:00', '0000-00-00 00:00:00');
"
```

**Both `ps_product.on_sale` and `ps_product_shop.on_sale` need updating** - PrestaShop's `Product` class defines `on_sale` as a shop-scoped field (`'shop' => true` in `Product::$definition`), so the webservice's `on_sale` display field (and, seemingly, `filter[on_sale]` too - not fully confirmed, see `.doc/ARCHITECTURE.md`) actually reads/writes `ps_product_shop`. Updating only the legacy `ps_product` column was tried first and left `filter[on_sale]=1` matching correctly while the returned `on_sale` field for those same products still read back `0` - a confusing half-working state that's easy to reintroduce if this fixture is ever redone by hand instead of copy-pasted.

This is a persisted fixture (unlike the throwaway WPML test double below) - the "Sale Test" page in the dev environment uses `[prestaspot product_count="6" on_sale="yes"]` and expects exactly these two products (Hummingbird t-shirt/sweater) to show up, each with a "Sale" badge. If it ever needs resetting, set both `on_sale` columns back to `0` for products 1/2 plus deleting the matching `ps_specific_price` rows reverts it.

### Testing stock status

Every fixture product's `ps_stock_available.quantity` (`id_product_attribute=0` row) is nonzero by default, so a plain `[prestaspot show_stock_status="yes"]` block already shows "In Stock" everywhere - not enough on its own to confirm the "Out of Stock" and "shop doesn't track stock" paths both work. Product 3 ("The best is yet to come' Framed poster", already visible on the "Sale Test" page) is a **persisted** fixture for the out-of-stock case:

```bash
docker exec prestaspot-docker-ps-db-1 mysql -uprestashop -pprestashop prestashop -e "UPDATE ps_stock_available SET quantity=0 WHERE id_product=3 AND id_product_attribute=0;"
```

To exercise the shop-wide "stock not tracked" fallback, temporarily flip `PS_STOCK_MANAGEMENT` off and clear the transient cache - every stock label (including product 3's "Out of Stock") should disappear entirely, not flip to some other state:

```bash
docker exec prestaspot-docker-ps-db-1 mysql -uprestashop -pprestashop prestashop -e "UPDATE ps_configuration SET value=0 WHERE name='PS_STOCK_MANAGEMENT' AND id_shop_group IS NULL AND id_shop IS NULL;"
```

Set it back to `1` afterward - this is a real shop setting, not a throwaway test flag, and other stock-status testing depends on it being on.

### Testing the multilingual language sync

The demo shop starts with a single language (English, `id_lang=1`), and there's no admin-UI-friendly way to add a second language (PrestaShop's "Add new language" form requires uploading flag/placeholder image files) - going straight through the DB is faster for a throwaway dev shop:

```bash
docker exec prestaspot-docker-ps-db-1 mariadb -uprestashop -pprestashop prestashop -e "
INSERT INTO ps_lang (id_lang, name, active, iso_code, language_code, locale, date_format_lite, date_format_full, is_rtl)
VALUES (2, 'Deutsch (Deutsch)', 1, 'de', 'de', 'de-DE', 'd.m.Y', 'd.m.Y H:i:s', 0);
INSERT INTO ps_lang_shop (id_lang, id_shop) VALUES (2, 1);
INSERT INTO ps_product_lang (id_product, id_shop, id_lang, name, description, description_short, link_rewrite, available_now, available_later)
VALUES (1, 1, 2, 'Kolibri-bedrucktes T-Shirt', '<p>Testbeschreibung.</p>', '<p>Deutsche Kurzbeschreibung.</p>', 'kolibri-bedrucktes-t-shirt', '', '');
"
```

Then install Polylang (`Plugins → Add New → search "Polylang"`), add a German language alongside English (Polylang's own "Add new language" admin form works fine, no file uploads required there), and create a page in each language with `[prestaspot product_count="1" show_description="yes"]` - the German page's language is set via the `post_lang_choice` `<select>` in the block editor's "Page" sidebar tab, under a "Languages" panel. Verify the two pages render different product name/description text, and that deactivating Polylang falls both back to the shop's default language without errors.

**WPML** is a paid plugin with no free download, so it can't be installed the same way. To test that code path anyway, drop a tiny test-double plugin (never commit this - it's a throwaway dev artifact) into the container's plugin directory - `docker cp` mangles `/var/www/...`-style paths under Git Bash on Windows unless `MSYS_NO_PATHCONV=1` is exported first:

```php
<?php
/** Plugin Name: Fake WPML (test double) */
if (!defined('ABSPATH')) exit;
if (!defined('ICL_SITEPRESS_VERSION')) define('ICL_SITEPRESS_VERSION', '4.6.0');
add_filter('wpml_current_language', fn() => get_option('fake_wpml_lang', 'en'));
add_filter('wpml_active_languages', fn() => array(
    'en' => array('language_code' => 'en', 'default_locale' => 'en_US'),
    'de' => array('language_code' => 'de', 'default_locale' => 'de_DE'),
));
```

Deactivate Polylang first (it takes precedence when both are active - useful for testing that precedence, but not while isolating the WPML path), activate the test double, flip the `fake_wpml_lang` option between `en`/`de` directly in the DB to switch languages, and verify the same way as above. Remove the test double directory with `docker exec ... rm -rf` afterward rather than the plugins-page "Delete" button - files created via `docker exec` end up root-owned, which the `www-data`-run PHP process can't delete itself.

---

## Coding Conventions

- **No JS build tooling.** Editor JS (`blocks/product-list/index.js`) is written directly against `window.wp.*` globals with `element.createElement` - no JSX, no webpack, no npm. Keep it that way; introducing `@wordpress/scripts` would be a deliberate, discussed decision, not an incremental addition.
- **Dependency injection, not singletons**, for everything except the top-level `Presta_Spot_Plugin` bootstrap itself. `Presta_Spot_Renderer`, `Presta_Spot_Shortcode`, `Presta_Spot_Block` etc. all receive their dependencies via constructor.
- **One renderer, two entry points.** Shortcode and block must never duplicate rendering logic - both funnel through `Presta_Spot_Renderer::render()`. If a new display option needs new markup, it goes in `templates/product-cards.php`, not in the shortcode or block class.
- **Two-tier config**: every display option (product count, columns, layout, show/hide flags, and any future one) has a global default in `Presta_Spot_Settings` and can be overridden per shortcode/block instance. See the worked example below - and see `.doc/ARCHITECTURE.md`'s note on the `!empty()` vs `array_key_exists()` sentinel convention before adding a boolean option; picking the wrong one is a silent bug.
- **PHP 8.0+ syntax is fine** (typed properties, union/`mixed` return types, `fn()` arrows) but avoid anything newer (PHP 8.1 enums/readonly, 8.3 typed constants) - see `Requires PHP: 8.0` in the plugin header. This is a deliberate divergence from the DinkyChat reference project, which targets PHP 8.4+; PrestaShop-integrated sites are more likely to be on older shared hosting than a standalone WordPress chat plugin.

---

## Worked Example: Adding a New Display Option

Using `show_image` as the reference (a boolean); swap the sentinel logic described in ARCHITECTURE.md if the new option is numeric/string instead.

1. **`includes/class-presta-spot-settings.php`** - add a `public const` key, add it to `PRESTASPOT_SETTINGS_DEFAULTS`, fetch it in `get_all()`, sanitize it there too.
2. **`includes/class-presta-spot-admin.php`** - `register_setting()` it in `register_settings()` with an appropriate `sanitize_callback`.
3. **`templates/settings-page.php`** - add the form field. If it's a checkbox, follow the `<input type="hidden" value="0">` + checkbox pattern already used for the other three toggles (see ARCHITECTURE.md's "Checkbox persistence gotcha"). Also add a `<?php echo $prestaspot_reset_button('prestaspot_<key>', '<default>'); ?>` next to its label (skip this only for `shop_url`/`api_key` - see ARCHITECTURE.md's "Per-Field Reset Buttons").
4. **`includes/class-presta-spot-renderer.php`** - resolve the value in `render()` using the correct sentinel convention, add it to the docblock's `@param array{...}` shape, and pass it to the template (any variable in scope when `include`-ing is visible to the template - no explicit passing needed).
5. **`templates/product-cards.php`** (or wherever the new option affects output) - use the resolved value.
6. **`includes/class-presta-spot-shortcode.php`** - add the shortcode attribute (empty-string/zero sentinel default), and only add it to `$render_args` when the caller actually specified it.
7. **`blocks/product-list/block.json`** - add the attribute with its `type` and `default`.
8. **`blocks/product-list/index.js`** - add the corresponding control (`ToggleControl`, `RangeControl`, etc.) in the relevant `PanelBody`.
9. **`includes/class-presta-spot-block.php`** - map the camelCase block attribute to the snake_case renderer arg in `render()`.
10. Bump the version in `prestaspot.php`, `blocks/product-list/block.json`, and `blocks/product-list/index.asset.php` (all three, kept in sync).

---

## Before Committing

- `php -l` every changed `.php` file (no PHP interpreter needed beyond what's already on this machine).
- Validate `block.json` is still valid JSON if touched.
- Bring up the Docker test stack (or reuse a running one - the plugin folder is bind-mounted, so no rebuild is needed) and manually verify in the browser:
  - Settings page saves and persists (including any new checkbox's *unchecked* state, per the hidden-field gotcha).
  - The block inserts, its Inspector controls work, and its `ServerSideRender` preview matches what the block.json defaults would produce.
  - The shortcode renders correctly both with and without the new attribute specified (i.e. both the override and the settings-default fallback path).
- Check `docker logs prestaspot-docker-wordpress-1` and `wp-content/debug.log` inside the container for PHP warnings/notices/errors, and the browser console for JS errors.
