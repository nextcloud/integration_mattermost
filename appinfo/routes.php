<?php

/**
 * Nextcloud - Mattermost
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2022
 */

return [
	'routes' => [
		['name' => 'config#isUserConnected', 'url' => '/is-connected', 'verb' => 'GET'],
		['name' => 'config#getFilesToSend', 'url' => '/files-to-send', 'verb' => 'GET'],
		['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setSensitiveConfig', 'url' => '/sensitive-config', 'verb' => 'PUT'],
		['name' => 'config#setWebhooksConfig', 'url' => '/webhooks', 'verb' => 'POST'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'config#setSensitiveAdminConfig', 'url' => '/sensitive-admin-config', 'verb' => 'PUT'],
		['name' => 'config#popupSuccessPage', 'url' => '/popup-success', 'verb' => 'GET'],

		['name' => 'mattermostAPI#sendMessage', 'url' => '/sendMessage', 'verb' => 'POST'],
		['name' => 'mattermostAPI#sendPublicLinks', 'url' => '/sendPublicLinks', 'verb' => 'POST'],
		['name' => 'mattermostAPI#sendFile', 'url' => '/sendFile', 'verb' => 'POST'],
		['name' => 'mattermostAPI#getChannels', 'url' => '/channels', 'verb' => 'GET'],
		['name' => 'mattermostAPI#getNotifications', 'url' => '/notifications', 'verb' => 'GET'],
		['name' => 'mattermostAPI#getMattermostUrl', 'url' => '/url', 'verb' => 'GET'],
		['name' => 'mattermostAPI#getUserAvatar', 'url' => '/users/{userId}/image', 'verb' => 'GET'],
		['name' => 'mattermostAPI#getTeamAvatar', 'url' => '/teams/{teamId}/image', 'verb' => 'GET'],

		['name' => 'files#getFileImage', 'url' => '/preview', 'verb' => 'GET'],
	]
];
