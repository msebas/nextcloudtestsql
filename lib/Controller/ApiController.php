<?php

declare(strict_types=1);

namespace OCA\NextcloudTestSql\Controller;

use OCA\NextcloudTestSql\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\DB\Exception as DatabaseException;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class ApiController extends Controller {
	public const CONFIG_KEY = 'api_key';
	public const HEADER_NAME = 'X-SQL-Debug-Key';

	public function __construct(
		string $appName,
		IRequest $request,
		private IDBConnection $connection,
		private IAppConfig $appConfig,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Execute one parameterized SQL statement.
	 *
	 * The app must only be enabled in disposable test instances. The configured
	 * access key is required even though this route deliberately has no Nextcloud
	 * login or CSRF protection.
	 *
	 * @return JSONResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED|Http::STATUS_SERVICE_UNAVAILABLE, array{affectedRows: int}|array{error: string}, array{}>
	 *
	 * 200: Statement executed
	 * 400: Request or SQL is invalid
	 * 401: Access key is missing or invalid
	 * 503: No access key is configured
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/v1/execute')]
	public function execute(): JSONResponse {
		$requestParameters = $this->getRequestParameters();
		if ($requestParameters instanceof JSONResponse) {
			return $requestParameters;
		}

		['sql' => $sql, 'parameters' => $parameters] = $requestParameters;

		try {
			/**
			 * Executing caller-provided SQL is the sole purpose of this test-only app.
			 * The database abstraction replaces *PREFIX* and binds the parameters.
			 *
			 * @psalm-suppress TaintedSql
			 * @psalm-suppress InvalidArgument
			 */
			$affectedRows = $this->connection->executeStatement($sql, $parameters);
		} catch (DatabaseException $exception) {
			$message = $exception->getMessage();

			return new JSONResponse(
				['error' => $message === '' ? 'SQL execution failed.' : 'SQL execution failed: ' . $message],
				Http::STATUS_BAD_REQUEST,
			);
		}

		return new JSONResponse(['affectedRows' => $affectedRows]);
	}

	/**
	 * Execute one parameterized SQL query and return all rows.
	 *
	 * The app must only be enabled in disposable test instances. The configured
	 * access key is required even though this route deliberately has no Nextcloud
	 * login or CSRF protection.
	 *
	 * @return JSONResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED|Http::STATUS_SERVICE_UNAVAILABLE, array{rows: list<array<string, mixed>>}|array{error: string}, array{}>
	 *
	 * 200: Query executed
	 * 400: Request or SQL is invalid
	 * 401: Access key is missing or invalid
	 * 503: No access key is configured
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/v1/select')]
	public function select(): JSONResponse {
		$requestParameters = $this->getRequestParameters();
		if ($requestParameters instanceof JSONResponse) {
			return $requestParameters;
		}

		['sql' => $sql, 'parameters' => $parameters] = $requestParameters;

		try {
			/**
			 * Querying caller-provided SQL is the sole purpose of this test-only app.
			 * The database abstraction replaces *PREFIX* and binds the parameters.
			 *
			 * @psalm-suppress TaintedSql
			 * @psalm-suppress InvalidArgument
			 */
			$result = $this->connection->executeQuery($sql, $parameters);
			try {
				/** @var list<array<string, mixed>> $rows */
				$rows = $result->fetchAll();
			} finally {
				$result->closeCursor();
			}
		} catch (DatabaseException $exception) {
			$message = $exception->getMessage();

			return new JSONResponse(
				['error' => $message === '' ? 'SQL query failed.' : 'SQL query failed: ' . $message],
				Http::STATUS_BAD_REQUEST,
			);
		}

		return new JSONResponse(['rows' => $rows]);
	}

	/**
	 * @return array{sql: string, parameters: list<bool|float|int|string|null>}|JSONResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED|Http::STATUS_SERVICE_UNAVAILABLE, array{error: string}, array{}>
	 */
	private function getRequestParameters(): array|JSONResponse {
		$configuredKey = $this->appConfig->getValueString(
			Application::APP_ID,
			self::CONFIG_KEY,
		);

		if ($configuredKey === '') {
			return new JSONResponse(
				['error' => 'SQL Debug is disabled because no access key is configured.'],
				Http::STATUS_SERVICE_UNAVAILABLE,
			);
		}

		$providedKey = $this->request->getHeader(self::HEADER_NAME);
		if ($providedKey === '' || !hash_equals($configuredKey, $providedKey)) {
			return new JSONResponse(
				['error' => 'Missing or invalid SQL Debug access key.'],
				Http::STATUS_UNAUTHORIZED,
			);
		}

		$sql = $this->request->getParam('sql');
		if (!is_string($sql) || trim($sql) === '') {
			return new JSONResponse(
				['error' => 'The request must contain a non-empty string field named "sql".'],
				Http::STATUS_BAD_REQUEST,
			);
		}

		$parameters = $this->request->getParam('parameters', []);
		if (!is_array($parameters) || !array_is_list($parameters)) {
			return new JSONResponse(
				['error' => 'The "parameters" field must be a JSON array.'],
				Http::STATUS_BAD_REQUEST,
			);
		}

		foreach ($parameters as $parameter) {
			if ($parameter !== null && !is_scalar($parameter)) {
				return new JSONResponse(
					['error' => 'SQL parameters must be strings, numbers, booleans, or null.'],
					Http::STATUS_BAD_REQUEST,
				);
			}
		}

		/** @var list<bool|float|int|string|null> $parameters */
		return ['sql' => $sql, 'parameters' => $parameters];
	}
}
