<?php
/**
 * Script to update the release-info.json file
 * 
 * This is meant to be run during GitHub Actions after a new release is created
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Arguments: version, release date
if ($argc < 3) {
    die("Usage: php update-release-info.php <version> <release_date>\n");
}

$version = $argv[1];
$releaseDate = $argv[2];

// Read the existing release-info.json
$jsonFile = dirname(__DIR__) . '/release-info.json';

if (!file_exists($jsonFile)) {
    die("Error: release-info.json file not found\n");
}

$releaseInfo = json_decode(file_get_contents($jsonFile), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error parsing release-info.json: " . json_last_error_msg() . "\n");
}

// Update the current version
$releaseInfo['current_version'] = $version;
$releaseInfo['last_updated'] = date('Y-m-d H:i:s');

// Add the new version to the versions list
$releaseInfo['versions'][$version] = [
    'version' => $version,
    'zip_url' => "https://github.com/your-username/webp-avif-test/releases/download/v{$version}/wp-image-optimizer.zip",
    'requires' => $releaseInfo['requires'],
    'tested' => $releaseInfo['tested'],
    'requires_php' => $releaseInfo['requires_php'],
    'release_date' => $releaseDate
];

// Update the changelog
$changelog = "<h4>{$version} - " . date('F Y') . "</h4>\n";
$changelog .= "<ul>\n";

// Try to fetch changelog from CHANGELOG.md
$changelogFile = dirname(__DIR__) . '/CHANGELOG.md';
if (file_exists($changelogFile)) {
    $changelogContent = file_get_contents($changelogFile);
    
    // Extract the latest version changes
    preg_match('/## \[' . preg_quote($version, '/') . '\].*?\n(.*?)(?=\n## |$)/s', $changelogContent, $matches);
    
    if (!empty($matches[1])) {
        $changes = preg_replace('/### (.*?)\n/', '<li><strong>$1</strong>:</li>', $matches[1]);
        $changes = preg_replace('/- (.*?)(\n|$)/', '<li>$1</li>', $changes);
        $changelog .= $changes;
    } else {
        $changelog .= "<li>New version {$version} released</li>\n";
    }
} else {
    $changelog .= "<li>New version {$version} released</li>\n";
}

$changelog .= "</ul>\n";

// Prepend the new changelog to the existing one
$releaseInfo['sections']['changelog'] = $changelog . $releaseInfo['sections']['changelog'];

// Write back to the file
file_put_contents(
    $jsonFile,
    json_encode($releaseInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "Successfully updated release-info.json for version {$version}\n";
