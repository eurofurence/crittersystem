#!/usr/bin/env sh
#

set -e
export COMPOSER_ALLOW_SUPERUSER=1

echo
echo "Running tests..."
echo
echo "----------------------------------------------------------"
echo "> composer validate"
echo "----------------------------------------------------------"
composer validate

echo "----------------------------------------------------------"
echo "> composer phpcs"
echo "----------------------------------------------------------"
composer phpcs

echo "----------------------------------------------------------"
echo "> composer phpstan"
echo "----------------------------------------------------------"
composer phpstan
