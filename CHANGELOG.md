# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 1.0.1 – 2022-11-08
### Changed
- sort channels by last post date, use most recent as default selection
- update npm pkgs
- switch to @nextcloud/vue dashboard components

### Fixed
- gracefully catch error when creating a share link with a password that does not respect NC's password policy
- fix selected radio elements border color
- fix api controller methods being restricted to admin users
- avoid deselecting channel when selecting the selected one

## 1.0.0 – 2022-09-09
### Added
* the app
