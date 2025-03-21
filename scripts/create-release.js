#!/usr/bin/env node

/**
 * WordPress Plugin Release Creator
 * 
 * A simple, clean script to create new plugin releases with auto-generated changelogs
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const readline = require('readline');

// Create readline interface for user input
const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

// Helper for prompting user
const prompt = (question) => new Promise((resolve) => {
  rl.question(question, (answer) => resolve(answer));
});

// Execute git command and return output
const git = (command) => {
  try {
    return execSync(`git ${command}`, { encoding: 'utf8' }).trim();
  } catch (error) {
    console.error(`Error executing git command: ${command}`);
    console.error(error.message);
    process.exit(1);
  }
};

// Get latest git tag
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

// Get commits since last tag
function getCommitsSinceLastTag(latestTag) {
  try {
    if (latestTag === 'No tags found') {
      // If no tags exist, get all commits
      const output = execSync('git log --pretty=format:"%s" --no-merges', { encoding: 'utf8' });
      return output.trim().split('\n').filter(Boolean);
    } else {
      // Get commits since the last tag
      const output = execSync(`git log ${latestTag}..HEAD --pretty=format:"%s" --no-merges`, { encoding: 'utf8' });
      return output.trim().split('\n').filter(Boolean);
    }
  } catch (error) {
    console.error('Error getting commits:', error.message);
    return [];
  }
}

// Process commit messages into changelog entries
function processCommitsForChangelog(commits) {
  if (commits.length === 0) {
    return [];
  }

  // Define patterns for different types of changes
  const patterns = [
    { regex: /^fix\(?\S*\)?\s*:\s*(.+)/i, type: 'Fix' },
    { regex: /^feat\(?\S*\)?\s*:\s*(.+)/i, type: 'Feature' },
    { regex: /^improve\(?\S*\)?\s*:\s*(.+)/i, type: 'Improvement' },
    { regex: /^refactor\(?\S*\)?\s*:\s*(.+)/i, type: 'Code Refactoring' },
    { regex: /^docs\(?\S*\)?\s*:\s*(.+)/i, type: 'Documentation' },
    { regex: /^perf\(?\S*\)?\s*:\s*(.+)/i, type: 'Performance' },
  ];

  const changelogEntries = [];
  
  commits.forEach(commit => {
    // Skip version bump commits
    if (commit.match(/^bump version/i)) {
      return;
    }
    
    let matched = false;
    
    // Try to match conventional commit patterns
    for (const pattern of patterns) {
      const match = commit.match(pattern.regex);
      if (match) {
        changelogEntries.push(`${pattern.type}: ${match[1]}`);
        matched = true;
        break;
      }
    }
    
    // If no patterns matched, add as general change
    if (!matched) {
      // Capitalize first letter and ensure it ends with period
      let processedCommit = commit;
      processedCommit = processedCommit.charAt(0).toUpperCase() + processedCommit.slice(1);
      if (!processedCommit.endsWith('.')) processedCommit += '.';
      
      changelogEntries.push(processedCommit);
    }
  });
  
  return changelogEntries;
}

// Main function
async function main() {
  const pluginDir = path.resolve(__dirname, '..');
  process.chdir(pluginDir);
  
  console.log('\n🚀 WordPress Plugin Release Creator\n');
  
  // Check if we're in a git repository
  try {
    execSync('git rev-parse --is-inside-work-tree', { stdio: 'ignore' });
  } catch (error) {
    console.error('❌ Error: Not in a git repository');
    process.exit(1);
  }
  
  // Get latest tag
  const latestTag = getLatestTag();
  
  // Get current version from plugin file
  const pluginFile = path.join(pluginDir, 'wp-image-optimizer.php');
  const pluginContent = fs.readFileSync(pluginFile, 'utf8');
  const currentVersionMatch = pluginContent.match(/Version: ([0-9.]+)/);
  const currentVersion = currentVersionMatch ? currentVersionMatch[1] : '0.0.0';
  
  console.log(`Current plugin version: ${currentVersion}`);
  console.log(`Latest git tag: ${latestTag}`);
  
  // Check if there are uncommitted changes
  const hasChanges = execSync('git status --porcelain', { encoding: 'utf8' }).trim().length > 0;
  if (hasChanges) {
    console.log('⚠️  Warning: You have uncommitted changes');
  }
  
  // Get and process commits for suggested changelog
  const commits = getCommitsSinceLastTag(latestTag);
  const suggestedChangelog = processCommitsForChangelog(commits);
  
  // Show suggested changelog if there are commits
  if (suggestedChangelog.length > 0) {
    console.log('\n📋 Auto-generated changelog from commits:');
    suggestedChangelog.forEach((entry, index) => {
      console.log(`  - ${entry}`);
    });
  } else if (latestTag !== 'No tags found') {
    console.log('\n⚠️ No commits found since the last tag');
  }
  
  // Ask for new version
  const newVersion = await prompt(`\nEnter new version (current is ${currentVersion}): `);
  if (!newVersion || !/^\d+\.\d+\.\d+$/.test(newVersion)) {
    console.error('❌ Error: Version must be in format X.Y.Z');
    process.exit(1);
  }
  
  // Use auto-generated changelog
  let changelogItems = [];
  if (suggestedChangelog.length > 0) {
    changelogItems = suggestedChangelog;
  } else {
    changelogItems.push('New release');
  }
  
  // Confirm before proceeding
  console.log('\n🔍 Summary:');
  console.log(`- New version: ${newVersion}`);
  console.log('- Changelog:');
  changelogItems.forEach(item => console.log(`  - ${item}`));
  
  const confirmation = await prompt('\nCreate this release? (y/n): ');
  if (confirmation.toLowerCase() !== 'y') {
    console.log('Operation cancelled');
    process.exit(0);
  }
  
  console.log('\n🔧 Creating release...');
  
  // Update plugin file version
  let updatedPluginContent = pluginContent
    .replace(/Version: [0-9.]+/, `Version: ${newVersion}`)
    .replace(/define\(\s*'WP_IMAGE_OPTIMIZER_VERSION',\s*'[0-9.]+'\s*\);/, `define( 'WP_IMAGE_OPTIMIZER_VERSION', '${newVersion}' );`);
  
  fs.writeFileSync(pluginFile, updatedPluginContent);
  console.log('✓ Updated version in plugin file');
  
  // Update or create CHANGELOG.md
  const changelogFile = path.join(pluginDir, 'CHANGELOG.md');
  let changelogContent = '';
  
  if (fs.existsSync(changelogFile)) {
    changelogContent = fs.readFileSync(changelogFile, 'utf8');
  }
  
  // Format new changelog entry
  const date = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
  let newChangelogEntry = `## [${newVersion}] - ${date}\n\n`;
  changelogItems.forEach(item => {
    newChangelogEntry += `- ${item}\n`;
  });
  newChangelogEntry += '\n';
  
  // If changelog already has content, insert after the title
  if (changelogContent.includes('# Changelog')) {
    changelogContent = changelogContent.replace('# Changelog\n', `# Changelog\n\n${newChangelogEntry}`);
  } else {
    changelogContent = `# Changelog\n\n${newChangelogEntry}${changelogContent}`;
  }
  
  fs.writeFileSync(changelogFile, changelogContent);
  console.log('✓ Updated CHANGELOG.md');
  
  // Git operations
  try {
    git('add wp-image-optimizer.php CHANGELOG.md');
    git(`commit -m "Bump version to ${newVersion}"`);
    git(`tag -a "v${newVersion}" -m "Version ${newVersion}"`);
    console.log('✓ Changes committed and tagged');
    
    // Ask to push
    const pushConfirmation = await prompt('\nPush changes to GitHub? (y/n): ');
    if (pushConfirmation.toLowerCase() === 'y') {
      console.log('Pushing changes...');
      git('push origin master');
      git(`push origin v${newVersion}`);
      console.log(`\n✅ Release v${newVersion} created and pushed!`);
      console.log('The GitHub Actions workflow will now build and publish the release.');
    } else {
      console.log('\nTo complete the release later, run:');
      console.log(`git push origin master && git push origin v${newVersion}`);
    }
  } catch (error) {
    console.error('❌ Git operation failed:', error.message);
    process.exit(1);
  }
  
  rl.close();
}

main().catch(error => {
  console.error('❌ An error occurred:', error);
  process.exit(1);
});
