<?php

declare(strict_types=1);

namespace OCA\NextcloudTestSql\SetupChecks;

use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class DangerousAppEnabled implements ISetupCheck {
	/** @psalm-suppress PossiblyUnusedMethod Instantiated by Nextcloud's dependency injection container. */
	public function __construct(
		private IL10N $l10n,
	) {
	}

	public function getCategory(): string {
		return 'security';
	}

	public function getName(): string {
		return $this->l10n->t('SQL Debug app is enabled');
	}

	public function run(): SetupResult {
		return SetupResult::warning(
			$this->l10n->t('SQL Debug is active. This app allows arbitrary database changes over HTTP and must never be installed or enabled on production systems.'),
		);
	}
}
