# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 2.2.0 – 2025-12-01

### Changed
* Set max NC version to 33 (#39) @kyteinsky
* lazy config migration (#39) @kyteinsky

### Added
* migrate to vue3 (#40) (#41) @kyteinsky


## 2.1.1 – 2025-07-18

### Changed
* Set min NC version to 30 (#36) @kyteinsky
* change upload icon to UploadBoxOutline (#37) @kyteinsky


## 2.1.0 – 2025-07-08

### Changed
* Maintenance updates (#30, #31) @kyteinsky
* Bump max NC version to 32 (#30) @kyteinsky
* Use outline icons instead of solid ones (#33) @kyteinsky


## 2.0.2 – 2025-01-07

### Fixed
* second fix for un-named channels and groups @kyteinsky


## 2.0.1 – 2024-11-18

### Fixed
* fetch all available channels + cache them @kyteinsky
* user icons in conversation list @kyteinsky
* only show conversations user is part of @kyteinsky


## 2.0.0 – 2024-11-13

### Changed
* Encrypt user tokens and refresh tokens @julien-nc
* Migrate the existing stored values @julien-nc
* Update npm pkgs @julien-nc
* Bump max nc version to 31 @julien-nc
* Name groups on id if name absent @kyteinsky

### Added
* Add password confirmation when setting the client id and secret @julien-nc

### Fixed
* Fix small style issues in send modal @julien-nc
* Fix file upload after breaking change in the Slack API @julien-nc
* Fix group channel name @julien-nc


## 1.2.0 – 2024-07-26

### Changed
* update composer deps @kyteinsky
* update gh workflows @kyteinsky
* bump max NC version to 30 @kyteinsky


## 1.1.0 - 2024-03-14

### Added

* composer setup, node update (vue8), add gh workflows @kyteinsky
* alphabetical ordering of conversations @kyteinsky
* compatibility with NC 29 @kyteinsky

### Fixed

* Migrate to new file actions for 28 @provokateurin
* update tests/bootstrap.php file @kyteinsky


## 1.0.1 - 2023-08-22

### Added

* redirect uri in oauth requests

### Fixed

* slack profile pictures url fix


## 1.0.0 – 2023-07-20

### Added

* the app
