<?php
/**
 * Nextcloud - Slack
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @author Anupam Kumar <kyteinsky@gmail.com>
 * @copyright Julien Veyssier 2022
 * @copyright Anupam Kumar 2023
 */

return [
	'routes' => [
		['name' => 'config#isUserConnected', 'url' => '/is-connected', 'verb' => 'GET'],
		['name' => 'config#getFilesToSend', 'url' => '/files-to-send', 'verb' => 'GET'],
		['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'config#setSensitiveAdminConfig', 'url' => '/sensitive-admin-config', 'verb' => 'PUT'],
		['name' => 'config#popupSuccessPage', 'url' => '/popup-success', 'verb' => 'GET'],

		['name' => 'slackAPI#sendMessage', 'url' => '/sendMessage', 'verb' => 'POST'],
		['name' => 'slackAPI#sendPublicLinks', 'url' => '/sendPublicLinks', 'verb' => 'POST'],
		['name' => 'slackAPI#sendFile', 'url' => '/sendFile', 'verb' => 'POST'],
		['name' => 'slackAPI#getChannels', 'url' => '/channels', 'verb' => 'GET'],
		['name' => 'slackAPI#getUserAvatar', 'url' => '/users/{slackUserId}/image', 'verb' => 'GET'],

		['name' => 'files#getFileImage', 'url' => '/preview', 'verb' => 'GET'],
	]
];
