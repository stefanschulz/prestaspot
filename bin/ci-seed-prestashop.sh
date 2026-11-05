#!/usr/bin/env bash
# Waits for the PrestaShop from .docker/ci-compose.yml to finish its
# auto-install, then sets up what the Integration suite expects (the
# fixtures documented in .doc/DEVELOPER_GUIDE.md):
#   - webservice on, with a read-only (GET) key
#   - products 1 and 2 on sale (with a price reduction), product 3 out of stock
#   - a German language with a translation of product 1
#
# Env: CI_PS_HOST (default localhost), CI_PS_PORT (default 8180),
#      PRESTASPOT_TEST_API_KEY (default: the CI-only key below),
#      CI_PS_TIMEOUT seconds to wait for the install (default 900).
set -euo pipefail

cd "$(dirname "$0")/.."

HOST="${CI_PS_HOST:-localhost}"
PORT="${CI_PS_PORT:-8180}"
API_KEY="${PRESTASPOT_TEST_API_KEY:-PRESTASPOTCITESTKEY00000000000AB}"
TIMEOUT="${CI_PS_TIMEOUT:-900}"
# COMPOSE_FILE (docker compose's own variable) can add override files locally.
export COMPOSE_FILE="${COMPOSE_FILE:-.docker/ci-compose.yml}"
COMPOSE=(docker compose)
SQL=("${COMPOSE[@]}" exec -T ps-db mariadb -uroot -proot prestashop)

if [[ ! "$API_KEY" =~ ^[A-Za-z0-9]{32}$ ]]; then
    echo "API key must be exactly 32 alphanumeric characters" >&2
    exit 1
fi

echo "Waiting for the PrestaShop install (up to ${TIMEOUT}s)..."
deadline=$((SECONDS + TIMEOUT))
until [[ "$("${SQL[@]}" -N -e "SELECT COUNT(*) FROM ps_product" 2>/dev/null || echo 0)" -ge 19 ]] \
    && curl -s -o /dev/null -f -L "http://$HOST:$PORT/" 2>/dev/null; do
    # The image's installer exits the container when the install fails.
    if ! "${COMPOSE[@]}" ps --status running --services | grep -qx prestashop; then
        echo "The PrestaShop container stopped - the install failed" >&2
        "${COMPOSE[@]}" logs --tail=60 prestashop >&2 || true
        exit 1
    fi
    if (( SECONDS > deadline )); then
        echo "PrestaShop did not become ready in time" >&2
        "${COMPOSE[@]}" logs --tail=60 prestashop >&2 || true
        exit 1
    fi
    sleep 10
done
echo "PrestaShop is up."

"${SQL[@]}" <<SQLEOF
-- A fresh install has no PS_WEBSERVICE row at all (it appears once someone toggles it in the back office).
DELETE FROM ps_configuration WHERE name = 'PS_WEBSERVICE';
INSERT INTO ps_configuration (name, value, date_add, date_upd) VALUES ('PS_WEBSERVICE', '1', NOW(), NOW());

DELETE FROM ps_webservice_account WHERE description = 'PrestaSpot CI';
INSERT INTO ps_webservice_account (\`key\`, description, class_name, is_module, module_name, active)
VALUES ('$API_KEY', 'PrestaSpot CI', 'WebserviceRequest', 0, NULL, 1);
SET @account = LAST_INSERT_ID();
INSERT INTO ps_webservice_account_shop (id_webservice_account, id_shop) VALUES (@account, 1);
INSERT INTO ps_webservice_permission (resource, method, id_webservice_account) VALUES
  ('products', 'GET', @account), ('currencies', 'GET', @account), ('categories', 'GET', @account),
  ('configurations', 'GET', @account), ('stock_availables', 'GET', @account), ('languages', 'GET', @account);

UPDATE ps_product SET on_sale = 1 WHERE id_product IN (1, 2);
UPDATE ps_product_shop SET on_sale = 1 WHERE id_product IN (1, 2);
INSERT INTO ps_specific_price (id_specific_price_rule, id_cart, id_product, id_shop, id_shop_group, id_currency, id_country, id_group, id_customer, id_product_attribute, price, from_quantity, reduction, reduction_tax, reduction_type, \`from\`, \`to\`)
VALUES
  (0, 0, 1, 1, 0, 0, 0, 0, 0, 0, -1, 1, 0.20, 1, 'percentage', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
  (0, 0, 2, 1, 0, 0, 0, 0, 0, 0, -1, 1, 0.15, 1, 'percentage', '0000-00-00 00:00:00', '0000-00-00 00:00:00');

UPDATE ps_stock_available SET quantity = 0 WHERE id_product = 3 AND id_product_attribute = 0;

INSERT INTO ps_lang (id_lang, name, active, iso_code, language_code, locale, date_format_lite, date_format_full, is_rtl)
VALUES (2, 'Deutsch (Deutsch)', 1, 'de', 'de', 'de-DE', 'd.m.Y', 'd.m.Y H:i:s', 0);
INSERT INTO ps_lang_shop (id_lang, id_shop) VALUES (2, 1);
INSERT INTO ps_product_lang (id_product, id_shop, id_lang, name, description, description_short, link_rewrite, available_now, available_later)
VALUES (1, 1, 2, 'Kolibri-bedrucktes T-Shirt', '<p>Testbeschreibung.</p>', '<p>Deutsche Kurzbeschreibung.</p>', 'kolibri-bedrucktes-t-shirt', '', '');
SQLEOF

status="$(curl -s -o /dev/null -w '%{http_code}' -u "$API_KEY:" "http://$HOST:$PORT/api/products?output_format=JSON&limit=1")"
if [[ "$status" != "200" ]]; then
    echo "Webservice check failed: HTTP $status" >&2
    exit 1
fi
echo "Seeded. Webservice answers 200 for the CI key."
