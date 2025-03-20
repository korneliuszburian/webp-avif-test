#!/usr/bin/env node

/**
 * Interactive Release Creation Tool
 * 
 * A modern, cross-platform script to create new plugin releases
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

// Helper for multiline input (for changelog)
const promptMultiline = async (question) => {
  console.log(question);
  console.log('(Enter your items, one per line. Type "DONE" on a new line when finished)');
  
  const lines = [];
  
  return new Promise((resolve) => {
    const onLine = (line) => {
      if (line.trim().toUpperCase() === 'DONE') {
        rl.off('line', onLine);
        resolve(lines);
      } else {
        lines.push(line);
      }
    };
    
    rl.on('line', onLine);
  });
};

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
    { regex: /^test\(?\S*\)?\s*:\s*(.+)/i, type: 'Tests' },
    { regex: /^build\(?\S*\)?\s*:\s*(.+)/i, type: 'Build System' },
    { regex: /^ci\(?\S*\)?\s*:\s*(.+)/i, type: 'CI' },
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
  
  console.log('\n📦 WordPress Plugin Release Creator 📦\n');
  
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
    console.log('\n📋 Suggested changelog entries from commits:');
    suggestedChangelog.forEach((entry, index) => {
      console.log(`${index + 1}. ${entry}`);
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
  
  // Get changelog items - use auto-generated by default
  let changelogItems = [];
  
  if (suggestedChangelog.length > 0) {
    console.log('\n✅ Using auto-generated changelog entries');
    changelogItems = suggestedChangelog;
  } else {
    console.log('\n⚠️ No commits found to generate changelog. Please enter changelog items manually:');
    changelogItems = await promptMultiline('Enter changelog items:');
  }
  if (changelogItems.length === 0) {
    console.error('❌ Error: Changelog cannot be empty');
    process.exit(1);
  }
  
  // Confirm changes
  console.log('\n📋 Summary:');
  console.log(`- New version: ${newVersion}`);
  console.log('- Changelog items:');
  changelogItems.forEach(item => console.log(`  - ${item}`));
  
  const confirmation = await prompt('\nConfirm these changes? (y/n): ');
  if (confirmation.toLowerCase() !== 'y') {
    console.log('Operation cancelled');
    process.exit(0);
  }
  
  console.log('\n🚀 Creating release...');
  
  // Update plugin file version
  let updatedPluginContent = pluginContent
    .replace(/Version: [0-9.]+/, `Version: ${newVersion}`)
    .replace(/define\(\s*'WP_IMAGE_OPTIMIZER_VERSION',\s*'[0-9.]+'\s*\);/, `define( 'WP_IMAGE_OPTIMIZER_VERSION', '${newVersion}' );`);
  
  fs.writeFileSync(pluginFile, updatedPluginContent);
  console.log('✅ Updated version in plugin file');
  
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
  console.log('✅ Updated CHANGELOG.md');
  
  // Git operations
  try {
    git('add wp-image-optimizer.php CHANGELOG.md');
    git(`commit -m "Bump version to ${newVersion}"`);
    git(`tag -a "v${newVersion}" -m "Version ${newVersion}"`);
    console.log('✅ Changes committed and tagged');
    
    const pushConfirmation = await prompt('\nPush changes to origin? (y/n): ');
    if (pushConfirmation.toLowerCase() === 'y') {
      console.log('Pushing changes...');
      git('push origin master');
      git(`push origin v${newVersion}`);
      console.log(`\n✅ Release v${newVersion} created and pushed to GitHub!`);
      console.log('GitHub Actions will now build and publish the release.');
    } else {
      console.log('\n📝 To complete the release, run these commands:');
      console.log('  git push origin master');
      console.log(`  git push origin v${newVersion}`);
    }
  } catch (error) {
    console.error('❌ Git operation failed:', error.message);
    process.exit(1);
  }
  
  rl.close();
}

// Run the main function
main().catch(error => {
  console.error('❌ An error occurred:', error);
  process.exit(1);
});
