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
        ['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
        ['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
        ['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
        ['name' => 'mattermostAPI#getEvents', 'url' => '/events', 'verb' => 'GET'],
        ['name' => 'mattermostAPI#getTodos', 'url' => '/todos', 'verb' => 'GET'],
        ['name' => 'mattermostAPI#markTodoAsDone', 'url' => '/todos/{id}/mark-done', 'verb' => 'PUT'],
        ['name' => 'mattermostAPI#getMattermostUrl', 'url' => '/url', 'verb' => 'GET'],
        ['name' => 'mattermostAPI#getProjectAvatar', 'url' => '/avatar/project', 'verb' => 'GET'],
        ['name' => 'mattermostAPI#getUserAvatar', 'url' => '/avatar/user', 'verb' => 'GET'],
    ]
];
