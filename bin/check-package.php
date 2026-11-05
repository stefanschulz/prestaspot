<?php
/**
 * Sanity-checks a built release ZIP before it is published.
 *
 *   php bin/check-package.php .releases/prestaspot-X.Y.Z.zip
 *
 * - one top-level folder, prestaspot/, with the main plugin file in it
 * - the version in the shipped plugin header matches the file name
 * - the ZIP holds exactly the files of the prestaspot/ source folder
 *   (so no dev tooling leaks in, nothing is missing)
 * - every PHP file in it parses
 * - the .sha256 file matches the ZIP
 */

$zip_path = $argv[1] ?? '';
if ('' === $zip_path || !is_file($zip_path)) {
    fwrite(STDERR, "Usage: php bin/check-package.php <zip>\n");
    exit(2);
}

$errors = array();
$zip = new ZipArchive();
if (true !== $zip->open($zip_path)) {
    fwrite(STDERR, "ERROR: cannot open $zip_path\n");
    exit(1);
}

$entries = array();
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (!str_ends_with($name, '/')) {
        $entries[] = $name;
    }
}
sort($entries);

foreach ($entries as $name) {
    if (!str_starts_with($name, 'prestaspot/')) {
        $errors[] = "entry outside prestaspot/: $name";
    }
}

$source_dir = dirname(__DIR__) . '/prestaspot';
$expected = array();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source_dir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $expected[] = 'prestaspot/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($source_dir) + 1));
    }
}
sort($expected);
foreach (array_diff($expected, $entries) as $name) {
    $errors[] = "missing from the ZIP: $name";
}
foreach (array_diff($entries, $expected) as $name) {
    $errors[] = "unexpected in the ZIP: $name";
}

$main = $zip->getFromName('prestaspot/prestaspot.php');
if (false === $main || !preg_match('/^\s*\*\s*Version:\s*([\d.]+)\s*$/m', $main, $m)) {
    $errors[] = 'prestaspot/prestaspot.php or its Version header is missing';
} elseif (basename($zip_path) !== "prestaspot-{$m[1]}.zip") {
    $errors[] = "file name " . basename($zip_path) . " does not match the plugin version {$m[1]}";
}

// php -l on the shipped bytes, not on the working tree.
$tmp = sys_get_temp_dir() . '/prestaspot-check-' . getmypid();
mkdir($tmp);
$zip->extractTo($tmp);
$zip->close();
foreach ($entries as $name) {
    if (str_ends_with($name, '.php')) {
        exec('php -l ' . escapeshellarg("$tmp/$name") . ' 2>&1', $output, $code);
        if (0 !== $code) {
            $errors[] = "syntax error in $name: " . implode(' ', $output);
        }
        $output = array();
    }
}
$cleanup = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($cleanup as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($tmp);

$sha_file = $zip_path . '.sha256';
if (!is_file($sha_file)) {
    $errors[] = 'checksum file ' . basename($sha_file) . ' is missing';
} elseif (strtok(trim(file_get_contents($sha_file)), ' ') !== hash_file('sha256', $zip_path)) {
    $errors[] = 'checksum file does not match the ZIP';
}

if ($errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: $error\n");
    }
    exit(1);
}
echo 'OK: ' . basename($zip_path) . ' (' . count($entries) . " files)\n";
