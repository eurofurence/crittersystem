# Changelog

## [Unreleased] V 2.0 - 2025-07-12

### Added

- New Theme21 - Space edition by @Balenty
- Website access mode: public, staff, admin
  - New entry at `event_config` table - `access_mode`
  - Public: Everyone can use the system
  - Staff: only `user.type.internal_staff` and `admin` can login
  - Admin: only `admin` can login
- Automated Docker image builds and publishing
- GitHub Container Registry (ghcr.io) integration
- Dynamic image tagging strategy:
  - Branch name for feature branches
  - 'latest' tag for main branch
  - Version number and 'latest' for release tags
- PHP_CodeSniffer quality gate before builds
- MkDocs integration
- New documentation folder structure with GitHub pages support
- New AdminV2 export functionality:
  - Export page routes
  - Download functionality
  - Import functionality under `/adminv2/export`
  - ExportController class
  - ShiftExportServiceProvider in app configuration
- New packages:
  - phpoffice/phpspreadsheet ^4.4
  - composer/pcre 3.3.2
  - maennchen/zipstream-php 3.1.2
- Enhanced HTTP Response class with new methods:
  - `download()` for file downloads
  - `redirectWithError()` for redirects with error messages
  - `redirectWithMessage()` for redirects with general messages

### Changed

- Refactored terminology from "angels/engels" to "critters" throughout the application
- Updated PHP coding standard description
- Updated MkDocs configuration for website and documentation
- Added backup of composer.lock file
- Updated permissions level requirements in documentation workflow
- Applied PSR-12 coding standards improvements:
  - Fixed method parameter spacing and alignment
  - Added strict type declarations
  - Adjusted spacing around operators and control structures
  - Removed unnecessary docblock return annotations
  - Cleaned up blank lines and indentation
  - Formatted string concatenation and HTML attributes consistently
  - Removed redundant property assignments in constructor
- Updated error handling with new 500 error template
- Renamed maintenance template from to `maintenance.html` > `maintenance2.html`
- Added new friendly page `maintenance.html`
- Updated configuration documentation with environment options clarification

### Fixed

- Docker build process now considers merge requests to prevent failing branch pushes
- PHP pages that failed code quality check
