[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](LICENSE)

# Critter System

The Critter System is a fork from [engelsystem](https://github.com/engelsystem/engelsystem). Since EF28 Eurofurence maintains their own fork.

Project [contribution list on GitHub](https://github.com/eurofurence/crittersystem/graphs/contributors)

Since the Critter System is open source, you can help improving it.
We really love to get pull requests containing fixes or improvements.
Please read the [CONTRIBUTING.md](CONTRIBUTING.md) and [DEVELOPMENT.md](docs/DEVELOPMENT.md) before you start.

## IMPORTANT

The project documents are currently being updated.

## Installation

The Critter System may be installed manually or by using the provided [docker setup](#docker).

### Requirements

- PHP >= 8.2
  - Required modules:
    - dom
    - json
    - mbstring
    - PDO
      - mysql
    - tokenizer
    - xml/libxml/SimpleXML
    - xmlwriter
- MySQL-Server >= 5.7.8 or MariaDB-Server >= 10.2.2
- Webserver, i.e. lighttpd, nginx, or Apache

### Download

- Go to the [Releases](https://github.com/eurofurence/crittersystem/releases) page and download the latest stable release file.
- Extract the files to your webroot and continue with the directions for configurations and setup.

### Configuration and Setup

- Folder `storage` and subfolders: Read+Write permisisons for the webserver
- Webserver:
  - Entry point: `public`
  - `mod_rewrite` must be enabled
  - Must read `.htaccess`
  - Directory Listing disabled
- MySQL database: set up with a user who has full rights to that database.
- If necessary, create a `config/config.php` to override values from `config/config.default.php`.
  - To disable/remove values from the following lists, set the value of the entry to `null`:
    - `themes`
    - `tshirt_sizes`
    - `headers`
    - `header_items`
    - `footer_items`
    - `locales`
    - `contact_options`
- To import the database, the `bin/migrate` script has to be run.
- In the browser, login with credentials `admin` : `asdfasdf` and change the password.

The Critter System can now be used.

### Session Settings

- Make sure the config allows for sessions.
- Both Apache and Nginx allow for different VirtualHost configurations.

### Docker

For instructions on how to build the Docker container for development, please consult the [DEVELOPMENT.md](docs/DEVELOPMENT.md).

#### Build

To build the `es_server` container:

```bash
cd docker
docker compose build
```

or to build the container by its own:

```bash
docker build -f docker/Dockerfile . -t es_server
```

#### Run

Start the Critter System

```bash
cd docker
docker compose up -d
```

#### Set Up / Migrate Database

Create the Database Schema (on a fresh install) or import database changes to migrate it to the newest version

```bash
cd docker
docker compose exec es_server bin/migrate
```

### Scripts

#### bin/deploy.sh

The `bin/deploy.sh` script can be used to deploy the Critter System. It uses rsync to deploy the application to a server over ssh.

For usage see `./bin/deploy.sh -h`

#### bin/migrate

The `bin/migrate` script can be used to import and update the database of the Critter System.

For more information on how to use it call `./bin/migrate help`

### Documentation

More documentation can be found at: add link here later
