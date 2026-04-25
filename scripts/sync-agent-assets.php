#!/usr/bin/env php
<?php
/**
 * Scans vendor packages for .agents/{commands,skills}/*.md files and creates
 * symlinks in both .claude/ and .agents/ project root directories.
 * Symlinks are prefixed with the vendor name (e.g. flyokai-).
 *
 * Run via: composer run sync-agent-assets
 * Or automatically on: post-install-cmd, post-update-cmd
 */

$projectRoot = dirname(__DIR__);
$vendorDir = $projectRoot . '/vendor';

$assetDirs = ['commands', 'skills'];
$targetRoots = ['.claude', '.agents'];

foreach ($targetRoots as $targetRoot) {
    foreach ($assetDirs as $assetDir) {
        $targetDir = $projectRoot . '/' . $targetRoot . '/' . $assetDir;

        // Clean stale symlinks
        if (is_dir($targetDir)) {
            foreach (new DirectoryIterator($targetDir) as $entry) {
                if ($entry->isDot() || !$entry->isLink()) {
                    continue;
                }
                if (!file_exists($entry->getPathname())) {
                    echo "  Removing stale symlink: $targetRoot/$assetDir/{$entry->getFilename()}\n";
                    unlink($entry->getPathname());
                }
            }
        }
    }
}

// Scan vendor/*/*/.agents/{commands,skills}/*.md
foreach ($assetDirs as $assetDir) {
    $pattern = $vendorDir . '/*/*/.agents/' . $assetDir . '/*.md';
    $files = glob($pattern);
    if (!$files) {
        continue;
    }

    foreach ($files as $file) {
        // Extract vendor name from path: vendor/{vendorName}/{package}/.agents/...
        $relToVendor = substr($file, strlen($vendorDir) + 1);
        $vendorName = explode('/', $relToVendor)[0];
        $basename = basename($file);
        $linkName = $vendorName . '-' . $basename;

        // Relative path from {targetRoot}/{assetDir}/ to vendor file
        $relVendorPath = substr($file, strlen($projectRoot) + 1);

        foreach ($targetRoots as $targetRoot) {
            $targetDir = $projectRoot . '/' . $targetRoot . '/' . $assetDir;
            $linkPath = $targetDir . '/' . $linkName;
            $relativePath = '../../' . $relVendorPath;

            if (is_link($linkPath)) {
                if (readlink($linkPath) === $relativePath) {
                    continue;
                }
                unlink($linkPath);
            }

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            echo "  Linking: $targetRoot/$assetDir/$linkName -> $relativePath\n";
            symlink($relativePath, $linkPath);
        }
    }
}

echo "Agent assets sync complete.\n";
