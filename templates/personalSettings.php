<?php
$appId = OCA\Slack\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-personalSettings');
?>

<div id="slack_prefs"></div>
