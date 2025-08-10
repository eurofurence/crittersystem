[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](LICENSE)

# Critter System

The Critter System is a fork from [engelsystem](https://github.com/engelsystem/engelsystem). Since EF28 Eurofurence maintains their own fork.

Project [contribution list on GitHub](https://github.com/eurofurence/crittersystem/graphs/contributors)

Since the Critter System is open source, you can help improving it.
We really love to get pull requests containing fixes or improvements.
Please read the [CONTRIBUTING.md](CONTRIBUTING.md) and [DEVELOPMENT.md](docs/src/DEVELOPMENT.md) before you start.

## IMPORTANT

The project documents are currently being updated.

The documents are located inside the folder `docs`.

## Installation

Once the system is deployed, open your browser and go to `/admin/install`. Example: `https://<SERVER_ADDRESS>/admin/install`.

Make sure your variables are set to enable the install feature:

```bash
# Enable the installation workflow interface
APP_ENABLE_INSTALL_WORKFLOW=true

# Set the admin password for accessing the install interface
APP_INITIAL_ADMIN_PASSWORD=your_secure_password_here
```