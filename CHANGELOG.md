# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 3.0.0 – 2025-12-23

### Changed
- npm and composer packages updated @janepie [#99](https://github.com/nextcloud/integration_mattermost/pull/99)
- lazy config loading and removing deprecated config method @janepie [#99](https://github.com/nextcloud/integration_mattermost/pull/99)
- migrated to vue3 @julien-nc [#100](https://github.com/nextcloud/integration_mattermost/pull/100)
- Use new settings components @julien-nc [#100](https://github.com/nextcloud/integration_mattermost/pull/100)
- Adjust to nc/files breaking changes for NC 33 @julien-nc [#100](https://github.com/nextcloud/integration_mattermost/pull/100)


## 2.1.1 – 2025-07-16

### Changed
- CSP Nonce updated @janepie [#57](https://github.com/nextcloud/integration_mattermost/pull/57)
- icons changed to outlined versions @janepie [#77](https://github.com/nextcloud/integration_mattermost/pull/77)
- npm packages updated @janepie [#77](https://github.com/nextcloud/integration_mattermost/pull/77)
- bump max supported NC version to 32 @janepie

## 2.1.0 - 2025-01-17

### Added

- admin setting to enable navigation link per default @janepie [#54](https://github.com/nextcloud/integration_mattermost/pull/54)

### Changed
- Encrypt client id/secret and user tokens @julien-nc [#52](https://github.com/nextcloud/integration_mattermost/pull/52)

## 2.0.0 – 2024-10-15

### Changed

- Use password confirmation for sensitive admin/personal setting values

### Fixed

- Only send Oauth credentials to the right URLs

## 1.1.1 – 2024-07-26

### Fixed

- update gh workflows and fix appstore build publish

## 1.1.0 – 2024-07-25

### Changed

- Bump max supported NC version to 30
- Update gh workflows

## 1.0.7 – 2024-03-13

### Fixed

- Use fallback image for icon urls
- Update node deps and fixes
- Update workflows from templates

## 1.0.6 – 2024-01-17

### Fixed

- better usage of file actions options
- minor fixes

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
