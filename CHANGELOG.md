# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 1.0.5 – 2023-11-02

### Changed

- replace NcMultiselect by NcSelect in files modal
- disable webhooks until Mattermost implements it
- Drop support for NC < 28

### Fixed

- Migrate to file actions for Nextcloud 28
- fix modal content width

## 1.0.4 – 2023-05-10

### Added

- daily calendar summary webhook
- upcoming events webhook
- smart picker provider for messages
- basic link preview for messages

### Changed

- update npm pkgs

### Fixed

- mistakes in translatable strings

## 1.0.3 – 2022-11-15
### Added
- Ability to send internal links
- translations

### Changed
- only send one message grouping optional comment + files
  [#11](https://github.com/julien-nc/integration_mattermost/issues/11) @joho1968
- use message permalinks instead of channel links as target for dashboard items and search results
  [#12](https://github.com/julien-nc/integration_mattermost/issues/12) @joho1968

### Fixed
- fix search result entries for direct messages (displayed text and link)
  [#12](https://github.com/julien-nc/integration_mattermost/issues/12) @joho1968

## 1.0.2 – 2022-11-09
### Added
- Ability to send files to direct user-user conversations

### Fixed
- stop using API endpoints which require to be a Mattermost admin
[#7](https://github.com/julien-nc/integration_mattermost/issues/7) @piratPavel

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
