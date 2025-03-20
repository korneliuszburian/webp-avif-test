/**
 * Build script for creating plugin release packages
 */
const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

// File patterns to exclude from the build
const excludePatterns = [
  '.git/**',
  '.github/**',
  '.vscode/**',
  'node_modules/**',
  'vendor/**',
  'tests/**',
  'scripts/**',
  '.husky/**',
  '.distignore',
  '.gitignore',
  '.editorconfig',
  '.eslintrc',
  '.stylelintrc',
  'package.json',
  'package-lock.json',
  'composer.json',
  'composer.lock',
  'phpcs.xml.dist',
  'phpstan.neon',
  'README.md',
  'CONTRIBUTING.md',
  '*.zip'
];

// Get package info
const pluginFile = fs.readFileSync('wp-image-optimizer.php', 'utf8');
const versionMatch = pluginFile.match(/Version:\s*([0-9.]+)/);
const version = versionMatch ? versionMatch[1] : '1.0.0';

console.log(`Building plugin version ${version}...`);

// Create output directory if it doesn't exist
const buildDir = path.resolve('build');
if (!fs.existsSync(buildDir)) {
  fs.mkdirSync(buildDir);
}

// Set up zip archive
const output = fs.createWriteStream(path.join(buildDir, 'wp-image-optimizer.zip'));
const archive = archiver('zip', {
  zlib: { level: 9 } // Maximum compression
});

// Pipe archive data to the file
archive.pipe(output);

// Function to check if file should be excluded
function isExcluded(filePath) {
  return excludePatterns.some(pattern => {
    if (pattern.endsWith('/**')) {
      const dir = pattern.slice(0, -3);
      return filePath.startsWith(dir);
    }
    return filePath === pattern;
  });
}

// Add files to the archive
function addDirectoryToArchive(directory, baseDir = '') {
  const entries = fs.readdirSync(directory, { withFileTypes: true });
  
  for (const entry of entries) {
    const fullPath = path.join(directory, entry.name);
    const archivePath = path.join(baseDir, entry.name);
    
    if (isExcluded(archivePath)) {
      continue;
    }
    
    if (entry.isDirectory()) {
      addDirectoryToArchive(fullPath, archivePath);
    } else {
      archive.file(fullPath, { name: archivePath });
    }
  }
}

// Start building the archive
addDirectoryToArchive('.');

// Finalize the archive
archive.finalize();

output.on('close', () => {
  console.log(`Build complete: ${archive.pointer()} total bytes written`);
  console.log(`Output: ${path.join(buildDir, 'wp-image-optimizer.zip')}`);
});

archive.on('error', (err) => {
  throw err;
});
