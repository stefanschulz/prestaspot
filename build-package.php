<?php
/**
 * PrestaSpot Build Script
 * Extracts version from prestaspot.php and creates package
 */

// Read version from plugin file
$plugin_file = __DIR__ . '/prestaspot/prestaspot.php';

if (!file_exists($plugin_file)) {
    die("ERROR: prestaspot/prestaspot.php not found\n");
}

$content = file_get_contents($plugin_file);
preg_match('/Version:\s*([\d.]+)/', $content, $matches);

if (empty($matches[1])) {
    die("ERROR: No valid Version number found in prestaspot/prestaspot.php!\n");
}
$version = $matches[1];

echo "Building prestaspot-$version.zip...\n";

// Delete ALL existing zip files before creating new one
$all_zips = glob(__DIR__ . '/prestaspot-*.zip');
if ($all_zips) {
    echo "Cleaning up old packages...\n";
    foreach ($all_zips as $zip_file) {
        unlink($zip_file);
        echo "  Deleted: " . basename($zip_file) . "\n";
    }
}

// Create zip file using PHP's ZipArchive
$zip = new ZipArchive();
$zip_file = __DIR__ . '/prestaspot-' . $version . '.zip';

if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("ERROR: Could not create zip file\n");
}

// Add entire prestaspot directory recursively, preserving structure
$source_dir = __DIR__ . '/prestaspot';
if (is_dir($source_dir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            // Get relative path and prepend 'prestaspot/' prefix for the zip archive
            $relative_path = str_replace($source_dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            // Use forward slashes (/) for cross-platform compatibility in zip paths
            $zip_entry_name = 'prestaspot/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative_path);
            $zip->addFile($file->getPathname(), $zip_entry_name);
        }
    }
}

$zip->close();

echo "Package created: prestaspot-$version.zip\n";
