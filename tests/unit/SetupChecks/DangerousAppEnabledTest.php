<?php

declare(strict_types=1);

namespace SetupChecks;

use OCA\NextcloudTestSql\SetupChecks\DangerousAppEnabled;
use OCP\IL10N;
use OCP\SetupCheck\SetupResult;
use PHPUnit\Framework\TestCase;

final class DangerousAppEnabledTest extends TestCase {
	public function testAlwaysReportsSecurityWarning(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n
			->method('t')
			->willReturnCallback(static fn (string $text): string => $text);

		$check = new DangerousAppEnabled($l10n);
		$result = $check->run();

		$this->assertSame('security', $check->getCategory());
		$this->assertSame('SQL Debug app is enabled', $check->getName());
		$this->assertSame(SetupResult::WARNING, $result->getSeverity());
		$this->assertSame(
			'SQL Debug is active. This app allows arbitrary database changes over HTTP and must never be installed or enabled on production systems.',
			$result->getDescription(),
		);
	}
}
