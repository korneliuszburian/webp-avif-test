#!/bin/bash

# Script to fix common coding standards issues

# Make sure we're in the root directory of the project
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
cd "$ROOT_DIR" || exit 1

echo "Fixing coding standards issues..."

# Run PHP Code Beautifier and Fixer if available
if [ -f vendor/bin/phpcbf ]; then
  echo "Running PHP Code Beautifier and Fixer..."
  vendor/bin/phpcbf --standard=phpcs.xml.dist --extensions=php .
fi

# Fix PHP file permissions
echo "Setting correct file permissions..."
find . -type f -name "*.php" -exec chmod 644 {} \;

# Ensure files use LF line endings
echo "Converting to LF line endings..."
find . -type f -name "*.php" -exec dos2unix -k {} \; 2>/dev/null || echo "dos2unix not installed, skipping line ending conversion"

echo "Done! Some issues may require manual fixing."
echo "To see remaining issues, run: composer phpcs"
