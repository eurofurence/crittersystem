#!/usr/bin/env sh
#

echo 
echo "Running tests..."
echo 
composer validate && composer phpcs && composer phpstan
