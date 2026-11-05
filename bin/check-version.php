<?php
/**
 * Verifies that every place the plugin version is written down agrees.
 *
 *   php bin/check-version.php [--expect=X.Y.Z]
 *
 * --expect additionally pins them all to a given version (the release
 * workflow passes the pushed tag's version).
 */

$root = dirname(__DIR__);

$sources = array(
    'prestaspot/prestaspot.php (Version header)' => array('prestaspot/prestaspot.php', '/^\s*\*\s*Version:\s*([\d.]+)\s*$/m'),
    'blocks/product-list/block.json' => array('prestaspot/blocks/product-list/block.json', '/"version":\s*"([\d.]+)"/'),
    'blocks/product-list/index.asset.php' => array('prestaspot/blocks/product-list/index.asset.php', "/'version'\s*=>\s*'([\d.]+)'/"),
    '.doc/ARCHITECTURE.md (Version line)' => array('.doc/ARCHITECTURE.md', '/^\*\*Version\*\*:\s*([\d.]+)\s*$/m'),
    '.doc/CHANGES.md (newest entry)' => array('.doc/CHANGES.md', '/^##\s+([\d.]+)\s/m'),
);

$expected = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--expect=')) {
        $expected = substr($arg, strlen('--expect='));
    }
}

$found = array();
foreach ($sources as $label => [$path, $pattern]) {
    $content = @file_get_contents("$root/$path");
    if (false === $content || !preg_match($pattern, $content, $m)) {
        fwrite(STDERR, "ERROR: no version found in $label\n");
        exit(1);
    }
    $found[$label] = $m[1];
}

$target = $expected ?? reset($found);
$failed = false;
foreach ($found as $label => $version) {
    $ok = $version === $target;
    echo ($ok ? 'ok     ' : 'MISMATCH ') . str_pad($version, 8) . $label . "\n";
    $failed = $failed || !$ok;
}

if ($failed) {
    fwrite(STDERR, "ERROR: version mismatch (expected $target)\n");
    exit(1);
}
echo "All versions are $target.\n";
