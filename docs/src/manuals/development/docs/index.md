---
title: Documents
---

How to update project documentation / Website

## Requirements

```shell
pip install mkdocs
pip install wheel
pip install pymdown-extensions
pip install mike
```

## Test / Deploy

This project is using Mike to deploy `gh-pages` with version.

=== "Local Test"
    Perform Local test of the docs. `http://127.0.0.1:8888`

    ```shell linenums="1"
    mkdocs serve
    ```

=== "Local Build"
    !!! notice
        Not needed to build if you are hosting on `gh-pages`. Output folder: `docs/public'

    ```shell linenums="1"
    # Build - local files (If desired)
    mkdocs build
    ```

=== "Publish - gh-pages"

    Content is published by Mike and uses `gh-pages`.

    References:

    - https://squidfunk.github.io/mkdocs-material/setup/setting-up-versioning/?h=mike#versioning
    - https://github.com/jimporter/mike

    ```shell linenums="1"
    # Deploy - default version: dev
    mike deploy [version]
    ```
