<?php

declare(strict_types=1);

namespace OCA\NextcloudTestSql\AppInfo;

use OCA\NextcloudTestSql\SetupChecks\DangerousAppEnabled;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'nextcloudtestsql';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerSetupCheck(DangerousAppEnabled::class);
	}

	public function boot(IBootContext $context): void {
	}
}
