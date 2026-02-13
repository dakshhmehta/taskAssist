<?php
#!/usr/bin/env php

/**
 * WPManage by Timepro - Release Script
 * Automates the release process for new plugin versions
 */

// Colors for output
const RED = "\033[0;31m";
const GREEN = "\033[0;32m";
const YELLOW = "\033[1;33m";
const BLUE = "\033[0;34m";
const NC = "\033[0m"; // No Color

// Directories
$scriptDir = __DIR__;
$pluginDir = $scriptDir . '/wpmanage-by-timepro';
$publicDir = $scriptDir . '/../public/wp';
$pluginFile = $pluginDir . '/wpmanage-by-timepro.php';
$jsonFile = $publicDir . '/wp-plugin-info.json';
$readmeFile = $pluginDir . '/readme.txt';

// Helper functions
function printHeader()
{
    echo BLUE . "================================" . NC . "\n";
    echo BLUE . "  WPManage Release Script" . NC . "\n";
    echo BLUE . "================================" . NC . "\n\n";
}

function printSuccess($msg)
{
    echo GREEN . "✓ " . $msg . NC . "\n";
}

function printError($msg)
{
    echo RED . "✗ " . $msg . NC . "\n";
    exit(1);
}

function printInfo($msg)
{
    echo YELLOW . "ℹ " . $msg . NC . "\n";
}

function getCurrentVersion($pluginFile)
{
    $content = file_get_contents($pluginFile);
    if (preg_match("/define\('WPMANAGE_VERSION', '(.*)'\)/", $content, $matches)) {
        return $matches[1];
    }
    return 'unknown';
}

function getNewVersion($currentVersion)
{
    echo YELLOW . "Current version: " . $currentVersion . NC . "\n";
    echo "Enter new version number (e.g., 0.0.3): ";
    $handle = fopen("php://stdin", "r");
    $newVersion = trim(fgets($handle));

    if (empty($newVersion)) {
        printError("Version number cannot be empty");
    }

    return $newVersion;
}

function getChangelog($version)
{
    echo "\n" . YELLOW . "Enter changelog items (one per line, empty line to finish):" . NC . "\n";
    $items = [];
    $handle = fopen("php://stdin", "r");

    while (true) {
        $line = trim(fgets($handle));
        if (empty($line)) break;
        $items[] = $line;
    }

    $changelog = "<h4>{$version}</h4>\n<ul>\n";
    foreach ($items as $item) {
        $changelog .= "<li>{$item}</li>\n";
    }
    $changelog .= "</ul>";

    return $changelog;
}

function updatePluginVersion($pluginFile, $newVersion)
{
    $content = file_get_contents($pluginFile);

    // Update constant
    $content = preg_replace(
        "/define\('WPMANAGE_VERSION', '.*'\)/",
        "define('WPMANAGE_VERSION', '{$newVersion}')",
        $content
    );

    // Update header
    $content = preg_replace(
        "/\* Version: .*/",
        "* Version: {$newVersion}",
        $content
    );

    file_put_contents($pluginFile, $content);
    printSuccess("Updated plugin file to version {$newVersion}");
}

function updateReadmeVersion($readmeFile, $newVersion)
{
    $content = file_get_contents($readmeFile);
    $content = preg_replace(
        "/Stable tag: .*/",
        "Stable tag: {$newVersion}",
        $content
    );
    file_put_contents($readmeFile, $content);
    printSuccess("Updated readme.txt to version {$newVersion}");
}

function createZip($scriptDir, $newVersion, $publicDir)
{
    $zipName = "wpmanage-by-timepro.zip";
    $zipPath = $publicDir . '/' . $zipName;

    if (file_exists($zipPath)) {
        unlink($zipPath);
    }

    // Create ZIP command (using system zip for exclusion patterns)
    $cmd = "cd " . escapeshellarg($scriptDir) . " && zip -r " . escapeshellarg($zipPath) . " wpmanage-by-timepro/ -x \"*.git*\" -x \"*.DS_Store\" -x \"*__MACOSX*\" > /dev/null 2>&1";
    exec($cmd, $output, $returnVar);

    if ($returnVar !== 0) {
        printError("Failed to create ZIP file");
    }

    $size = round(filesize($zipPath) / 1024, 2) . ' KB';
    printSuccess("Created ZIP file: {$zipName} ({$size})");
}

function updateJsonManifest($jsonFile, $newVersion, $changelog)
{
    $json = json_decode(file_get_contents($jsonFile), true);

    $json['version'] = $newVersion;
    $json['new_version'] = $newVersion;
    $json['last_updated'] = date('Y-m-d H:i:s');

    // Prepend changelog
    $oldChangelog = $json['sections']['changelog'] ?? '';
    $json['sections']['changelog'] = $changelog . "\n\n" . $oldChangelog;

    file_put_contents($jsonFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    printSuccess("Updated wp-plugin-info.json to version {$newVersion}");
}

// Main execution
printHeader();

// Verify files
if (!file_exists($pluginFile)) printError("Plugin file not found: {$pluginFile}");
if (!file_exists($jsonFile)) printError("JSON manifest not found: {$jsonFile}");
if (!is_dir($publicDir)) mkdir($publicDir, 0755, true);

// Get versions
$currentVersion = getCurrentVersion($pluginFile);
$newVersion = getNewVersion($currentVersion);

echo "\n" . YELLOW . "Version bump: {$currentVersion} → {$newVersion}" . NC . "\n";
echo "Continue? (y/n): ";
$handle = fopen("php://stdin", "r");
$confirm = trim(fgets($handle));

if (strtolower($confirm) !== 'y') {
    printError("Release cancelled");
}

// Get changelog
$changelog = getChangelog($newVersion);

echo "\n";
printInfo("Starting release process...\n");

// updates
updatePluginVersion($pluginFile, $newVersion);
updateReadmeVersion($readmeFile, $newVersion);
createZip($scriptDir, $newVersion, $publicDir);
updateJsonManifest($jsonFile, $newVersion, $changelog);

echo "\n";
printSuccess("Release {$newVersion} completed successfully!\n");

echo BLUE . "Release Summary:" . NC . "\n";
echo "  Version: {$newVersion}\n";
echo "  ZIP: {$publicDir}/wpmanage-by-timepro.zip\n";
echo "  JSON: {$jsonFile}\n\n";

echo YELLOW . "Next Steps:" . NC . "\n";
echo "  1. Test the plugin ZIP file\n";
echo "  2. Verify wp-plugin-info.json is correct\n";
echo "  3. Commit changes to git\n";
echo "  4. WordPress sites will auto-detect update within 24 hours\n\n";
