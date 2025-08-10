# Install Workflow Feature

This document describes the new `install-workflow` feature for Critter System.

## Overview

The install-workflow provides a web-based installation interface that allows administrators to:

- Check system status and database connectivity
- Execute database migrations with real-time output
- Troubleshoot database issues with a SQL console
- Monitor the installation process through a clean Bootstrap UI

## Configuration

Add the following environment variables to enable and configure the install workflow:

```bash
# Enable the installation workflow interface
APP_ENABLE_INSTALL_WORKFLOW=true

# Set the admin password for accessing the install interface
APP_INITIAL_ADMIN_PASSWORD=your_secure_password_here
```

## Features

### 1. **Authentication Step**

- Password-protected access using `APP_INITIAL_ADMIN_PASSWORD`
- Session-based authentication for the installation process

### 2. **System Status Check**

- Database connection verification
- Migration file status checks
- System information display (hostname, PHP version)

### 3. **Database Migration**

- Execute `bin/migrate` script through the web interface
- Real-time command output display
- Terminal interface using xterm.js for output streaming
- Automatic creation of migration status files

### 4. **SQL Console (Troubleshooting)**

- Execute SELECT, SHOW, DESCRIBE, and EXPLAIN queries
- Table-formatted results display
- Safe query execution (only read operations allowed... kinda... You can delete too to fix errors :D)

### 5. **Installation Completion**

- Automatic detection of `config/migration.ok` file
- Installation workflow becomes inaccessible after completion

## File Structure

### Controllers

- `src/Controllers/Admin/InstallController.php` - Main installation workflow controller

### Routes

- `/admin/install` - Main installation interface
- `/admin/install/authenticate` - Password authentication
- `/admin/install/status` - System status API
- `/admin/install/migrate` - Migration execution API
- `/admin/install/sql` - SQL console API

### Views

- `resources/views/install/install_index.twig` - Main installation interface (Twig template)
- `resources/views/install/installation_complete.twig` - Completion page (Twig template)
- `resources/views/install/install_disabled.twig` - Disabled state page (Twig template)

### Assets

- `resources/assets/js/install.js` - JavaScript functionality compiled via webpack
- `resources/assets/css/install.css` - JavaScript functionality compiled via webpack

Important: The assets need to be present inside the folder `public/assets/special`. Use `bin/prepare_instance` to setup everything for you. (If you are deploying by yourself or locally)

## Security Features

1. **Password Protection**: Access requires the setup admin password
2. **Installation State Check**: Interface is automatically disabled when installation is complete
3. **SQL Query Restrictions**: Only safe SELECT-type queries are allowed in the SQL console
4. **Session-based Authentication**: Authentication state is maintained in the session

## Integration with Existing System

### Migration Gate Middleware

The `MigrationGate` middleware has been updated to allow access to `/admin/install` routes, similar to how `/health` endpoints are handled.

### Configuration Integration

The feature integrates with the existing configuration system in `config.default.php` and respects the existing `APP_INITIAL_ADMIN_PASSWORD` setting.

## Dependencies

- **Frontend**: Bootstrap 5.3.7, Bootstrap Icons, xterm.js 5.5.0 (via `public/assets/special` folder)
- **Backend**: Uses existing Critter System dependencies, no additional PHP packages required
- **Build System**: JavaScript compiled via webpack, uses existing build configuration

## Usage Flow

1. **Enable the feature** by setting `APP_ENABLE_INSTALL_WORKFLOW=true`
2. **Set the password** via `APP_INITIAL_ADMIN_PASSWORD`
3. **Access the interface** at `/admin/install`
4. **Authenticate** with the setup password
5. **Check system status** to verify database connectivity
6. **Run migration** using the migration step
7. **Monitor progress** through the terminal output
8. **Troubleshoot** using the SQL console if needed
9. **Complete installation** - the interface becomes inaccessible

## Migration Status Files

The workflow monitors and creates these status files:

- `config/migration.ok` - Installation completed successfully
- `config/migration.fail` - Migration failed
- `config/migration.running` - Migration currently in progress

## Error Handling

- Network errors are displayed with user-friendly messages
- Migration failures are captured and displayed with full output
- SQL query errors are safely handled and displayed
- File system errors (missing migration files) are detected and reported
