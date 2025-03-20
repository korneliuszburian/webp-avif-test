#!/usr/bin/env node

/**
 * Quick Version Checker
 * 
 * This script displays the current plugin version and latest Git tag
 * without starting the full release process.
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Get plugin directory
const pluginDir = path.resolve(__dirname, '..');
process.chdir(pluginDir);

// Function to get latest git tag
function getLatestTag() {
  try {
    // Get all tags, sorted by creation date (newest first)
    const tags = execSync('git tag --sort=-creatordate', { encoding: 'utf8' })
      .trim()
      .split('\n')
      .filter(Boolean);
    
    if (tags.length === 0) {
      return 'No tags found';
    }
    
    // Return the most recent tag
    return tags[0];
  } catch (error) {
    return 'Unable to retrieve tags';
  }
}

// Function to get plugin version
function getPluginVersion() {
  try {
    const pluginFile = path.join(pluginDir, 'wp-image-optimizer.php');
    const pluginContent = fs.readFileSync(pluginFile, 'utf8');
    const versionMatch = pluginContent.match(/Version: ([0-9.]+)/);
    
    return versionMatch ? versionMatch[1] : 'Unknown';
  } catch (error) {
    return 'Unable to read plugin file';
  }
}

// Main function
function main() {
  console.log('\n🔍 WordPress Plugin Version Info\n');
  
  // Check if we're in a git repository
  try {
    execSync('git rev-parse --is-inside-work-tree', { stdio: 'ignore' });
  } catch (error) {
    console.error('❌ Error: Not in a git repository');
    process.exit(1);
  }
  
  // Get and display version info
  const currentVersion = getPluginVersion();
  const latestTag = getLatestTag();
  
  console.log(`📊 Plugin file version: ${currentVersion}`);
  console.log(`🏷️  Latest git tag: ${latestTag}`);
  
  // Check for version mismatch
  if (latestTag.replace('v', '') !== currentVersion && latestTag !== 'No tags found') {
    console.log('\n⚠️  Warning: Plugin version and latest tag don\'t match!');
  }
  
  // Check for uncommitted changes
  const hasChanges = execSync('git status --porcelain', { encoding: 'utf8' }).trim().length > 0;
  if (hasChanges) {
    console.log('⚠️  Warning: You have uncommitted changes');
    
    // Show list of uncommitted files
    console.log('\nUncommitted changes:');
    console.log(execSync('git status --short', { encoding: 'utf8' }));
  }
  
  console.log(''); // Add empty line at the end
}

// Run the main function
try {
  main();
} catch (error) {
  console.error('\n❌ An error occurred:', error.message);
  process.exit(1);
}
