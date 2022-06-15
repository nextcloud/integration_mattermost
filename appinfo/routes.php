<?php
/**
 * Nextcloud - Mattermost
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

return [
    'routes' => [
		['name' => 'config#isUserConnected', 'url' => '/is-connected', 'verb' => 'GET'],
		['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
        ['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
        ['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],

		['name' => 'mattermostAPI#getChannels', 'url' => '/channels', 'verb' => 'GET'],
		['name' => 'mattermostAPI#getNotifications', 'url' => '/notifications', 'verb' => 'GET'],
        ['name' => 'mattermostAPI#getMattermostUrl', 'url' => '/url', 'verb' => 'GET'],
        ['name' => 'mattermostAPI#getUserAvatar', 'url' => '/avatar/user', 'verb' => 'GET'],
        ['name' => 'mattermostAPI#getTeamAvatar', 'url' => '/avatar/team', 'verb' => 'GET'],

		['name' => 'files#getFileImage', 'url' => '/preview', 'verb' => 'GET'],
    ]
];
