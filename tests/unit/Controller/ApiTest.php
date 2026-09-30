<?php

declare(strict_types=1);

namespace Controller;

use OCA\NextcloudTestSql\AppInfo\Application;
use OCA\NextcloudTestSql\Controller\ApiController;
use OCP\AppFramework\Http;
use OCP\DB\Exception as DatabaseException;
use OCP\DB\IResult;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase {
	private IRequest $request;
	private IDBConnection $connection;
	private IAppConfig $appConfig;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->connection = $this->createMock(IDBConnection::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
	}

	public function testExecutesParameterizedStatement(): void {
		$sql = 'DELETE FROM *PREFIX*sd_entries WHERE user_id = ?';
		$parameters = ['system-test-user'];
		$this->configureRequest('test-key', $sql, $parameters);
		$this->configureAccessKey('test-key');
		$this->connection
			->expects($this->once())
			->method('executeStatement')
			->with($sql, $parameters)
			->willReturn(3);

		$response = $this->controller()->execute();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['affectedRows' => 3], $response->getData());
	}

	public function testSelectReturnsRows(): void {
		$sql = 'SELECT appid, configkey FROM *PREFIX*appconfig WHERE appid = ?';
		$parameters = ['nextcloudtestsql'];
		$rows = [
			['appid' => 'nextcloudtestsql', 'configkey' => 'api_key'],
		];
		$this->configureRequest('test-key', $sql, $parameters);
		$this->configureAccessKey('test-key');

		$result = $this->createMock(IResult::class);
		$result
			->expects($this->once())
			->method('fetchAll')
			->willReturn($rows);
		$result
			->expects($this->once())
			->method('closeCursor')
			->willReturn(true);
		$this->connection
			->expects($this->once())
			->method('executeQuery')
			->with($sql, $parameters)
			->willReturn($result);

		$response = $this->controller()->select();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['rows' => $rows], $response->getData());
	}

	public function testRejectsRequestWhenNoKeyIsConfigured(): void {
		$this->configureRequest('test-key', 'DELETE FROM *PREFIX*sd_entries', []);
		$this->configureAccessKey('');
		$this->connection->expects($this->never())->method('executeStatement');

		$response = $this->controller()->execute();

		$this->assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $response->getStatus());
		$this->assertSame(
			['error' => 'SQL Debug is disabled because no access key is configured.'],
			$response->getData(),
		);
	}

	public function testRejectsWrongKey(): void {
		$this->configureRequest('wrong-key', 'DELETE FROM *PREFIX*sd_entries', []);
		$this->configureAccessKey('test-key');
		$this->connection->expects($this->never())->method('executeStatement');

		$response = $this->controller()->execute();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(
			['error' => 'Missing or invalid SQL Debug access key.'],
			$response->getData(),
		);
	}

	public function testRejectsMissingKey(): void {
		$this->configureRequest('', 'DELETE FROM *PREFIX*sd_entries', []);
		$this->configureAccessKey('test-key');
		$this->connection->expects($this->never())->method('executeStatement');

		$response = $this->controller()->execute();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(
			['error' => 'Missing or invalid SQL Debug access key.'],
			$response->getData(),
		);
	}

	#[DataProvider('invalidPayloadProvider')]
	public function testRejectsInvalidPayload(
		mixed $sql,
		mixed $parameters,
		string $expectedError,
	): void {
		$this->configureRequest('test-key', $sql, $parameters);
		$this->configureAccessKey('test-key');
		$this->connection->expects($this->never())->method('executeStatement');

		$response = $this->controller()->execute();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => $expectedError], $response->getData());
	}

	/**
	 * @return array<string, array{mixed, mixed, string}>
	 */
	public static function invalidPayloadProvider(): array {
		return [
			'missing SQL' => [
				null,
				[],
				'The request must contain a non-empty string field named "sql".',
			],
			'empty SQL' => [
				'  ',
				[],
				'The request must contain a non-empty string field named "sql".',
			],
			'non-array parameters' => [
				'DELETE FROM *PREFIX*sd_entries',
				'value',
				'The "parameters" field must be a JSON array.',
			],
			'associative parameters' => [
				'DELETE FROM *PREFIX*sd_entries WHERE user_id = ?',
				['user_id' => 'system-test-user'],
				'The "parameters" field must be a JSON array.',
			],
			'nested parameter' => [
				'DELETE FROM *PREFIX*sd_entries WHERE user_id = ?',
				[['system-test-user']],
				'SQL parameters must be strings, numbers, booleans, or null.',
			],
		];
	}

	public function testReturnsDatabaseErrorWithoutExecutingAnotherStatement(): void {
		$sql = 'DELETE INVALID SQL';
		$this->configureRequest('test-key', $sql, []);
		$this->configureAccessKey('test-key');
		$this->connection
			->expects($this->once())
			->method('executeStatement')
			->with($sql, [])
			->willThrowException(new DatabaseException('syntax error'));

		$response = $this->controller()->execute();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(
			['error' => 'SQL execution failed: syntax error'],
			$response->getData(),
		);
	}

	public function testSelectReturnsDatabaseError(): void {
		$sql = 'SELECT INVALID SQL';
		$this->configureRequest('test-key', $sql, []);
		$this->configureAccessKey('test-key');
		$this->connection
			->expects($this->once())
			->method('executeQuery')
			->with($sql, [])
			->willThrowException(new DatabaseException('syntax error'));

		$response = $this->controller()->select();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(
			['error' => 'SQL query failed: syntax error'],
			$response->getData(),
		);
	}

	private function configureAccessKey(string $key): void {
		$this->appConfig
			->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, ApiController::CONFIG_KEY)
			->willReturn($key);
	}

	private function configureRequest(
		string $key,
		mixed $sql,
		mixed $parameters,
	): void {
		$this->request
			->method('getHeader')
			->with(ApiController::HEADER_NAME)
			->willReturn($key);
		$this->request
			->method('getParam')
			->willReturnCallback(
				static fn (string $name, mixed $default = null): mixed => match ($name) {
					'sql' => $sql,
					'parameters' => $parameters,
					default => $default,
				},
			);
	}

	private function controller(): ApiController {
		return new ApiController(
			Application::APP_ID,
			$this->request,
			$this->connection,
			$this->appConfig,
		);
	}
}
