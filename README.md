[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](LICENSE)

[![Docker Verify, Build and Publish](https://github.com/eurofurence/crittersystem/actions/workflows/docker-build.yml/badge.svg?branch=dev)](https://github.com/eurofurence/crittersystem/actions/workflows/docker-build.yml)

# Critter System

Shift planning and volunteer management system used at Eurofurence events. This repository contains the application
code, configuration, and documentation sources.

- Documentation site: https://eurofurence.github.io/crittersystem/
- Documentation sources: docs/src
- Original Fork: [engelsystem](https://github.com/engelsystem/engelsystem)

Since the Critter System is open source, you can help improving it.
We really love to get pull requests containing fixes or improvements.
Please read the [CONTRIBUTING.md](CONTRIBUTING.md) and [DEVELOPMENT.md](docs/src/DEVELOPMENT.md) before you start.

## Quick Start

### Requirements

- PHP 8.1+ with required extensions (pdo_mysql, mbstring, intl, gd, etc.)
- Composer
- Node.js 18+ and Yarn
- MariaDB/MySQL
- A web server pointing to public/ (or PHP built‑in server for local use)

### Local (Development)

1. Clone the repo and install dependencies:
  - composer install
  - yarn install && yarn build
2. Configure database and app settings:
  - Create `config\config.php` (based on `config\config.default.php`) or set environment variables.
  - Keep only the variables you want to override in `config\config.php`.
3. Create a database/schema in your DB server.
   - run `bin/migrate`
4. Serve the app locally (example):
  - `php -S 127.0.0.1:8000 -t public`
5. Run the installer in your browser:
  - http://127.0.0.1:8000/admin/install
  - Ensure these env vars are set for the installer:
    - `APP_ENABLE_INSTALL_WORKFLOW=true`
    - `APP_INITIAL_ADMIN_PASSWORD=your_secure_password_here`

### Docker

- Development (mounted sources):
  1) `cd docker\dev`
  2) `docker compose up -d`
  3) Open http://127.0.0.1/admin/install
    - Optional: edit `docker\dev\deployment.env` to set `APP_*` variables.

- Basic compose:
  1) `cd docker`
  2) `docker compose up -d`
  3) Open http://localhost/admin/install
    - Optional: edit `docker\deployment.env` to set `APP_*` variables.

## Notes

- Most project documentation is published via GitHub Pages and built with MkDocs (config: mkdocs.yml). For deeper guides
  and module docs, see the documentation site or browse docs/src.
- See CONTRIBUTING.md and SECURITY.md for contribution and security policies.
- Please use Critter‑centric terminology in contributions to keep naming consistent.
