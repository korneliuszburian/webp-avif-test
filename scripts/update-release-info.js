#!/usr/bin/env node

/**
 * Manual script to update release-info.json
 * This can be used to fix the GitHub Pages deployment issue
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Helper function to execute git commands
const git = (command) => {
  try {
    return execSync(`git ${command}`, { encoding: 'utf8' }).trim();
  } catch (error) {
    console.error(`Error executing git command: ${command}`);
    console.error(error.message);
    process.exit(1);
  }
};

// Main function
async function main() {
  const pluginDir = path.resolve(__dirname, '..');
  process.chdir(pluginDir);
  
  console.log('\n🚀 Manual Release Info Updater\n');
  
  // Get current version from plugin file
  const pluginFile = path.join(pluginDir, 'wp-image-optimizer.php');
  const pluginContent = fs.readFileSync(pluginFile, 'utf8');
  const currentVersionMatch = pluginContent.match(/Version: ([0-9.]+)/);
  const currentVersion = currentVersionMatch ? currentVersionMatch[1] : '0.0.0';
  
  console.log(`Current plugin version: ${currentVersion}`);
  
  // Get repository info
  const repoUrl = git('remote get-url origin');
  const repoMatch = repoUrl.match(/github\.com[:/]([^/]+)\/([^/.]+)/);
  
  if (!repoMatch) {
    console.error('Could not determine GitHub repository from remote URL');
    process.exit(1);
  }
  
  const repoOwner = repoMatch[1];
  const repoName = repoMatch[2];
  const repo = `${repoOwner}/${repoName}`;
  
  console.log(`GitHub repository: ${repo}`);
  
  // Extract changelog from CHANGELOG.md if it exists
  let changelog = `<h4>${currentVersion} - ${new Date().toLocaleString('en-US', { month: 'long', year: 'numeric' })}</h4><ul><li>Manual release update</li></ul>`;
  
  if (fs.existsSync('CHANGELOG.md')) {
    const changelogContent = fs.readFileSync('CHANGELOG.md', 'utf8');
    const match = changelogContent.match(new RegExp(`## \\[${currentVersion}\\].*?\\n([\\s\\S]*?)(?=\\n## |$)`, 'm'));
    
    if (match && match[1]) {
      const entries = match[1].trim().split('\n')
        .filter(line => line.trim().startsWith('-'))
        .map(line => line.trim().substring(1).trim());
      
      if (entries.length > 0) {
        changelog = `<h4>${currentVersion} - ${new Date().toLocaleString('en-US', { month: 'long', year: 'numeric' })}</h4><ul>`;
        entries.forEach(entry => {
          changelog += `<li>${entry}</li>`;
        });
        changelog += '</ul>';
      }
    }
  }
  
  // Create the release info JSON
  const date = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
  const dateTime = new Date().toISOString().replace('T', ' ').split('.')[0]; // YYYY-MM-DD HH:MM:SS
  
  const releaseInfo = {
    name: "WebP & AVIF Image Optimizer",
    slug: "wp-image-optimizer",
    version: currentVersion,
    download_url: `https://github.com/${repo}/releases/download/v${currentVersion}/wp-image-optimizer.zip`,
    author: "<a href='https://github.com/korneliuszburian'>Korneliusz Burian</a>",
    author_profile: "https://github.com/korneliuszburian",
    requires: "5.8",
    tested: "6.7",
    requires_php: "8.1",
    last_updated: dateTime,
    homepage: `https://github.com/${repo}`,
    sections: {
      description: "High-performance WebP and AVIF image conversion plugin for WordPress.",
      installation: "Upload the plugin to your WordPress site and activate it. Configure settings from the 'Settings > WebP & AVIF' menu.",
      changelog: changelog
    },
    icons: {
      "1x": `https://raw.githubusercontent.com/${repo}/master/assets/icon-128x128.png`,
      "2x": `https://raw.githubusercontent.com/${repo}/master/assets/icon-256x256.png`
    },
    versions: {
      [currentVersion]: {
        version: currentVersion,
        zip_url: `https://github.com/${repo}/releases/download/v${currentVersion}/wp-image-optimizer.zip`,
        requires: "5.8",
        tested: "6.7",
        requires_php: "8.1",
        release_date: date
      }
    },
    current_version: currentVersion
  };
  
  // Save the release info to a file
  const releaseInfoFile = path.join(pluginDir, 'release-info.json');
  fs.writeFileSync(releaseInfoFile, JSON.stringify(releaseInfo, null, 2));
  console.log(`✓ Created release-info.json for version ${currentVersion}`);
  
  console.log('\nTo update GitHub Pages:');
  console.log('1. Commit this file to the master branch');
  console.log('2. Switch to gh-pages branch: git checkout gh-pages');
  console.log('3. Copy the file: cp release-info.json ../release-info.json && mv ../release-info.json .');
  console.log('4. Commit and push: git add release-info.json && git commit -m "Update release-info.json" && git push origin gh-pages');
  console.log('5. Switch back to master: git checkout master');
}

// Run the main function
main().catch(error => {
  console.error('❌ An error occurred:', error);
  process.exit(1);
});
