# Slack integration into Nextcloud

This integration is hepful in sending files to your Slack workspace as original
files, public links to the files or as internal Nextcloud links.

## 🔧 Configuration

### User settings

The account configuration happens in the "Connected accounts" user settings section.
It requires you to authenticate your Slack account and allow the required permissions
for the app to work nicely.

A link to the "Connected accounts" user settings section will be displayed in the
files widget for users who didn't configure a Slack account.

### Admin settings

There also is a "Connected accounts" **admin** settings section for you to set the OAuth
authentication keys of your Slack app for all the users to use.

Access token can be optionally set to expire after a given time in the Slack app settings.
Refreshing of the token takes place automatically.

## 🖼️ Screenshots

![Files plugin](img/screenshot1.png)
![Sending internal links](img/screenshot2.png)
