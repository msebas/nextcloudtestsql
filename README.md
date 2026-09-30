# Nextcloud Test SQL

> [!CAUTION]
> This app allows arbitrary database queries and changes over HTTP. It is exclusively for disposable test instances. Never install or enable it on a production system.

Nextcloud Test SQL provides small HTTP endpoints for end-to-end test harnesses that cannot connect to the Nextcloud database directly. It supports parameterized read and write statements through Nextcloud's `IDBConnection`, including the standard `*PREFIX*` table placeholder.

The app supports Nextcloud 33 through 35 and PHP 8.1 or newer.

## Install and configure

Mount or copy this directory into a Nextcloud app directory, then enable it and configure a fresh random key for the container:

```sh
php occ app:enable nextcloudtestsql
SQL_DEBUG_KEY="$(openssl rand -hex 40)"
php occ config:app:set nextcloudtestsql api_key \
	--value="${SQL_DEBUG_KEY}" \
	--type=string \
	--sensitive
```

Keep `SQL_DEBUG_KEY` in the test runner and send it in the `X-SQL-Debug-Key` header. When `api_key` is absent or empty, both endpoints are disabled. The sensitive flag prevents the key from appearing in normal configuration listings.

While the app is enabled, Nextcloud's Administration overview permanently reports a security warning explaining that the app must not be used in production.

## Execute write SQL

Send one SQL statement to:

```text
POST /index.php/apps/nextcloudtestsql/api/v1/execute
Content-Type: application/json
X-SQL-Debug-Key: <container-specific key>
```

Example request:

```json
{
  "sql": "DELETE FROM *PREFIX*sd_entries WHERE user_id = ?",
  "parameters": ["system-test-user"]
}
```

Successful response:

```json
{
  "affectedRows": 2
}
```

The endpoint uses `IDBConnection::executeStatement`, which replaces `*PREFIX*`, binds the supplied parameters, and returns the affected-row count.

## Execute select SQL

Send a query that returns rows to:

```text
POST /index.php/apps/nextcloudtestsql/api/v1/select
Content-Type: application/json
X-SQL-Debug-Key: <container-specific key>
```

Example request:

```json
{
  "sql": "SELECT appid, configkey FROM *PREFIX*appconfig WHERE appid = ?",
  "parameters": ["nextcloudtestsql"]
}
```

Successful response:

```json
{
  "rows": [
    {
      "appid": "nextcloudtestsql",
      "configkey": "api_key"
    }
  ]
}
```

The endpoint uses `IDBConnection::executeQuery`, fetches all result rows as associative objects, and closes the result cursor.

Error responses contain an `error` string and use these status codes:

- `400` for an invalid request or a database error
- `401` for a missing or incorrect access key
- `503` when no access key is configured

## Tests

Install the Composer dependencies and run the unit tests:

```sh
composer install
composer test:unit
```

The HTTP integration check expects an installed, enabled, and configured test instance:

```sh
NEXTCLOUD_URL='https://nextcloud.test' \
SQL_DEBUG_KEY="${SQL_DEBUG_KEY}" \
tests/integration/api.sh
```

The integration check executes a harmless parameterized `DELETE` and `SELECT` against a deliberately nonexistent app-config entry, verifies both response formats, and verifies rejection of an incorrect key.
