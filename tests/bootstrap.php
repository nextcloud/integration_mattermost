<?php

require_once __DIR__ . '/../../../tests/bootstrap.php';

use OCP\App\IAppManager;

use OCA\Slack\AppInfo\Application;

\OC::$server->get(IAppManager::class)->loadApp(Application::APP_ID);
OC_Hook::clear();
