<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\NextcloudTestSql\AppInfo\Application::APP_ID, OCA\NextcloudTestSql\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\NextcloudTestSql\AppInfo\Application::APP_ID, OCA\NextcloudTestSql\AppInfo\Application::APP_ID . '-main');

?>

<div id="nextcloudtestsql"></div>
