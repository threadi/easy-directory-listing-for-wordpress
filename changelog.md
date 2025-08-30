# Changelog

## [3.6.0] - 30.08.2025

### Added

- Added export flag for each listing object

## [3.5.0] - 29.08.2025

### Added

- Cleanup the directory to thumbnails from directory listings (max age 1 day)

## [3.4.2] - 27.08.2025

### Changed

- Extended REST hook

## [3.4.1] - 26.08.2025

### Changed

- Check if constants are already defined

## [3.4.0] - 24.08.2025

### Added

- Added cancel button

### Changed

- Do not lowercase the requested directory in REST API
- Rename *_service_*_directory_loading hook to *_directory_listing_*_directory_loading

## [3.3.4] - 16.08.2025

### Added

- Added new hook before the tree is build

### Changed

- Delete transient of listing if any error occurred

### Fixed

- Fixed some typos

## [3.3.3] - 15.08.2025

### Added

- Added new hook to force further loading of directory or break it

### Fixed

- Fixed missed disabled loading of wp-admin for local service

## [3.3.2] - 15.08.2025

### Changed

- Optimized error handling in Local listing

## [3.3.1] - 10.08.2025

### Changed

- Allow HTML-code for form descriptions
- Small style optimizations

### Fixed

- Fixed wrong spelling in 3 fields API form
- Fixed usage of file form

## [3.3.0] - 09.08.2025

### Added

- Added new form for 3 fields API

## [3.2.0] - 03.08.2025

### Added

- Added open and closing for directories

### Changed

- Hide previous error on next login
- Set expiration for transients to one day

### Fixed

- Fixed spelling of "colSpan"

## [3.1.2] - 02.08.2025

### Fixed

- Fixed detection of thumbnails for local directory listings

## [3.1.1] - 27.07.2025

### Changed

- Hide taxonomy meta box

### Fixed

- Fixed capabilities for REST API requests

## [3.1.0] - 23.07.2025

### Added

- Added option to set capability for directory credentials

## [3.0.3] - 02.07.2025

### Changed

- Return errors if directory listing object report them
- Lowercase any given URL before processing it

## [3.0.2] - 09.06.2025

### Changed

- "wplogo.png" is now allowed
- Optimized output of server errors
- Optimized loading screen
- Better check for real images in local listings

### Fixed

- Fixed faulty check for directories to hide on local listing

## [3.0.1] - 01.06.2025

### Fixed

- colspan => colSpan
- Fixed missing loading screen on empty response array
- Fixed login form which does not initiate the tree on request

## [3.0.0] - 31.05.2025

### Added

- Directory-wise loading added for better performance with large directories and deep directory trees
- Now compatible with WordPress Coding Standards
- And also successfully checked by PHPStan
- Added some file type specific icons

### Changed

- Changes some text for better understanding
- Multiple optimization for better performance
- Active folder is now marked
- Show hint if chosen directory is empty

## [2.4.1] - 24.05.2025

### Added

- Added option to hide actions for single file

## [2.4.0] - 13.05.2025

### Added

- Added base function to use a optional custom URL for the object
- Added more hooks

## [2.3.0] - 11.05.2025

### Added

- Added directory listing functions for better individual usage of archive entries
- Added multiple hooks

## [2.2.2] - 01.05.2025

### Changed

- Moved set_actions() to base object

## [2.2.1] - 15.02.2025

### Changed

- Changed table style to WP default tables

### Fixed

- Fixed error on reload of local listing

## [2.2.0] - 09.02.2025

### Added

- Added description for each listing object
- Added possibility to disable a listing object

### Changed

- Output of errors optimized

## [2.1.2] - 02.02.2025

### Fixed

- Fixed REST URL handling

## [2.1.1] - 02.02.2025

### Added

- Added taxonomy status messages

### Changed

- Optimized error handling if using directory archives

## [2.1.0] - 01.02.2025

### Added

- All texts are now translatable by the WordPress-plugin which uses this package via set_translations() on Init-object

### Changed

- Button to submit is active if all fields are filled
- Directory archive can now be disabled

## [2.0.2] - 26.01.2025

### Added

- Added form for simple URL input

### Changed

- Optimized some code fragments

## [2.0.1] - 25.01.2025

### Changed

- Remove terms on uninstallation of plugin which uses this library

## [2.0.0] - 25.01.2025

### Added

- Added support for directory archives where you can save the credentials of your external connections
- Added encryption for any credential you enter and save in the directory archive

### Changed

- Optimized loading times of scripts

## [1.0.0] - 10.12.2024

### Added

- Initial Release