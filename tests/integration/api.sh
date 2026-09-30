#!/usr/bin/env bash

set -euo pipefail

: "${NEXTCLOUD_URL:?Set NEXTCLOUD_URL to the Nextcloud base URL}"
: "${SQL_DEBUG_KEY:?Set SQL_DEBUG_KEY to the configured app key}"

if (( ${#SQL_DEBUG_KEY} != 80 )); then
	printf 'SQL_DEBUG_KEY must contain exactly 80 characters.\n' >&2
	exit 1
fi

execute_endpoint="${NEXTCLOUD_URL%/}/index.php/apps/nextcloudtestsql/api/v1/execute"
execute_request_body='{"sql":"DELETE FROM *PREFIX*appconfig WHERE appid = ? AND configkey = ?","parameters":["nextcloudtestsql_integration_test","never-created"]}'
select_endpoint="${NEXTCLOUD_URL%/}/index.php/apps/nextcloudtestsql/api/v1/select"
select_request_body='{"sql":"SELECT appid, configkey FROM *PREFIX*appconfig WHERE appid = ? AND configkey = ?","parameters":["nextcloudtestsql_integration_test","never-created"]}'

call_endpoint() {
	local endpoint="$1"
	local key="$2"
	local request_body="$3"
	curl --silent --show-error \
		--request POST \
		--header 'Content-Type: application/json' \
		--header "X-SQL-Debug-Key: ${key}" \
		--data "${request_body}" \
		--write-out $'\n%{http_code}' \
		"${endpoint}"
}

success_response="$(call_endpoint "${execute_endpoint}" "${SQL_DEBUG_KEY}" "${execute_request_body}")"
success_status="${success_response##*$'\n'}"
success_body="${success_response%$'\n'*}"

if [[ "${success_status}" != '200' ]]; then
	printf 'Expected HTTP 200, received %s: %s\n' "${success_status}" "${success_body}" >&2
	exit 1
fi

if [[ ! "${success_body}" =~ \"affectedRows\"[[:space:]]*:[[:space:]]*[0-9]+ ]]; then
	printf 'Response does not contain affectedRows: %s\n' "${success_body}" >&2
	exit 1
fi

select_response="$(call_endpoint "${select_endpoint}" "${SQL_DEBUG_KEY}" "${select_request_body}")"
select_status="${select_response##*$'\n'}"
select_body="${select_response%$'\n'*}"

if [[ "${select_status}" != '200' ]]; then
	printf 'Expected HTTP 200, received %s: %s\n' "${select_status}" "${select_body}" >&2
	exit 1
fi

if [[ ! "${select_body}" =~ \"rows\"[[:space:]]*:[[:space:]]*\[ ]]; then
	printf 'Response does not contain rows: %s\n' "${select_body}" >&2
	exit 1
fi

unauthorized_response="$(call_endpoint "${execute_endpoint}" "${SQL_DEBUG_KEY}-wrong" "${execute_request_body}")"
unauthorized_status="${unauthorized_response##*$'\n'}"
unauthorized_body="${unauthorized_response%$'\n'*}"

if [[ "${unauthorized_status}" != '401' ]]; then
	printf 'Expected HTTP 401, received %s: %s\n' "${unauthorized_status}" "${unauthorized_body}" >&2
	exit 1
fi

printf 'SQL Debug integration test passed.\n'
