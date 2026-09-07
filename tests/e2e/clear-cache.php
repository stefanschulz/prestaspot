<?php
/**
 * Clears PrestaSpot's own product/category/language/currency transients
 * before the E2E run - the same manual step .doc/DEVELOPER_GUIDE.md tells a
 * human to do before every live re-test, automated here instead. Without
 * this, a spec can see stale cached data left over from a previous run or
 * from manual testing in the same dev stack.
 */
require '/var/www/html/wp-load.php';

global $wpdb;

$deleted = $wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_prestaspot\_%' OR option_name LIKE '\_transient\_timeout\_prestaspot\_%'"
);

echo "Cleared $deleted PrestaSpot transient row(s).\n";
