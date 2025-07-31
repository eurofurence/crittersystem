---
title: Web server Deploy
---

## TL;DR;

```shell title="Shell" linenums="1"
###############################################################################
# Commands from projects root folder
###############################################################################
# Composer Install
composer install

# Yarn
yarn install
yarn build

# Compile the language files
find resources/lang -type f -name '*.po' -exec sh -c 'msgfmt "${1%.*}.po" -o"${1%.*}.mo" && echo "Compiling: ${1%.*}.po > ${1%.*}.mo"' shell {} \;

# Create local config
cp config/config.default.php config/config.php

# Edit the file and set the database configuration
nano config/config.php

# Set storage permission (Assuming the group is already set to the webserver group)
chmod g+w -R storage

# Do the DB Migration
bin/migrate
```
