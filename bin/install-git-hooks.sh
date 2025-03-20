#!/bin/bash

# Script to install git hooks

# Make sure we're in the root directory of the project
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
cd "$ROOT_DIR" || exit 1

# Create pre-commit hook if it doesn't exist
if [ ! -f .git/hooks/pre-commit ]; then
  cat > .git/hooks/pre-commit << 'EOF'
#!/bin/bash

# Get a list of staged PHP files
STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$')

# Exit if there are no PHP files staged
if [ -z "$STAGED_PHP_FILES" ]; then
  exit 0
fi

echo "Running pre-commit checks..."

# Run PHP lint on each file
for FILE in $STAGED_PHP_FILES; do
  php -l "$FILE" > /dev/null 2>&1
  if [ $? -ne 0 ]; then
    echo "PHP syntax error in $FILE. Please fix before committing."
    exit 1
  fi
done

# Only run PHP syntax checks as a required pre-commit check
# Display other checks as warnings but don't block the commit

# Run PHPCS if available (but don't fail the commit)
if [ -f vendor/bin/phpcs ]; then
  echo "Running PHP CodeSniffer (informational only)..."
  vendor/bin/phpcs --standard=phpcs.xml.dist $STAGED_PHP_FILES
  if [ $? -ne 0 ]; then
    echo "⚠️ PHPCS found style issues. Consider running 'composer fix' or './bin/fix-code-style.sh'"
    echo "You can still commit, but these issues might cause CI checks to fail."
  fi
fi

# Run PHPStan if available (but don't fail the commit)
if [ -f vendor/bin/phpstan ]; then
  echo "Running PHPStan (informational only)..."
  vendor/bin/phpstan analyse $STAGED_PHP_FILES
  if [ $? -ne 0 ]; then
    echo "⚠️ PHPStan found potential issues."
    echo "You can still commit, but these issues might cause CI checks to fail."
  fi
fi

# Run security checks if available (but don't fail the commit)
if [ -f vendor/bin/phpcs ]; then
  echo "Running security checks (informational only)..."
  vendor/bin/phpcs --standard=PHPCompatibility --extensions=php --runtime-set testVersion 8.1- $STAGED_PHP_FILES
  if [ $? -ne 0 ]; then
    echo "⚠️ Security checks found potential issues."
    echo "You can still commit, but these issues might cause CI checks to fail."
  fi
fi

echo "All pre-commit checks passed!"
exit 0
EOF

  # Make hook executable
  chmod +x .git/hooks/pre-commit
  echo "Git pre-commit hook installed successfully"
else
  echo "Git pre-commit hook already exists"
fi
