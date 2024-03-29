# Mattermost integration into Nextcloud

This integration provides a search provider for Mattermost messages, a dashboard widget
showing your latest @mentions and lets you send files to a Mattermost channel directly in Nextcloud Files.

## 🔧 Configuration

### User settings

The account configuration happens in the "Connected accounts" user settings section.
It requires to create a personal access token in your Mattermost settings.

A link to the "Connected accounts" user settings section will be displayed in the widget
for users who didn't configure a Mattermost account.

### Admin settings

There also is a "Connected accounts" **admin** settings section if you want to allow
your Nextcloud users to use OAuth to authenticate to a specific Mattermost instance.
