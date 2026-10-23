#!/usr/bin/env bash
set -Eeuo pipefail

image="${1:?uso: test-infrastructure.sh IMAGEM [IMAGEM_TESTES]}"
test_image="${2:-}"
suffix="${GITHUB_RUN_ID:-local}-$$"
network="frequenciafacil-ci-$suffix"
db_container="frequenciafacil-ci-db-$suffix"
app_container="frequenciafacil-ci-app-$suffix"
test_container="frequenciafacil-ci-tests-$suffix"
db_password='ci-app-password'
root_password='ci-root-password'
response_dir="$(mktemp -d)"

# Invocada indiretamente pelo trap.
# shellcheck disable=SC2329
cleanup() {
  docker rm -f "$test_container" "$app_container" "$db_container" >/dev/null 2>&1 || true
  docker network rm "$network" >/dev/null 2>&1 || true
  rm -rf "$response_dir"
}
trap cleanup EXIT

assert_get() {
  local route="$1"
  local expected_type="$2"
  local expected_content="$3"
  local label="$4"
  local safe_name="${label//[^a-zA-Z0-9]/_}"
  local headers="$response_dir/$safe_name.headers"
  local body="$response_dir/$safe_name.body"
  local status content_type

  status="$(curl --silent --show-error \
    --dump-header "$headers" \
    --output "$body" \
    --write-out '%{http_code}' \
    "http://127.0.0.1:18080$route")"
  content_type="$(sed -n 's/^[Cc]ontent-[Tt]ype:[[:space:]]*//p' "$headers" | tail -n 1 | tr -d '\r')"

  if [[ "$status" != 200 ]]; then
    echo "$label retornou HTTP $status; esperado 200." >&2
    return 1
  fi
  if [[ ! "$content_type" =~ $expected_type ]]; then
    echo "$label retornou Content-Type '$content_type'; esperado '$expected_type'." >&2
    return 1
  fi
  if ! grep -Fq "$expected_content" "$body"; then
    echo "$label nao contem o marcador esperado: $expected_content" >&2
    return 1
  fi
}

modules="$(docker run --rm "$image" php -m)"
grep -qx 'mysqli' <<<"$modules"
grep -qx 'pdo_mysql' <<<"$modules"

apache_modules="$(docker run --rm "$image" apache2ctl -M 2>/dev/null)"
grep -q 'rewrite_module' <<<"$apache_modules"

docker network create "$network" >/dev/null
# Impede que o Git Bash no Windows converta /var/lib/mysql em um caminho do host.
MSYS_NO_PATHCONV=1 docker run -d \
  --name "$db_container" \
  --network "$network" \
  --network-alias db \
  --tmpfs /var/lib/mysql \
  --env MYSQL_DATABASE=frequenciafacil_test \
  --env MYSQL_USER=frequencia_app \
  --env MYSQL_PASSWORD="$db_password" \
  --env MYSQL_ROOT_PASSWORD="$root_password" \
  mysql:8.4 >/dev/null

for attempt in {1..90}; do
  if docker exec --env MYSQL_PWD="$db_password" "$db_container" \
    mysql --protocol=TCP --host=127.0.0.1 --user=frequencia_app frequenciafacil_test -e 'SELECT 1' >/dev/null 2>&1; then
    break
  fi
  if [[ "$attempt" == 90 ]]; then
    docker logs "$db_container"
    echo "MySQL nao ficou pronto para o smoke test." >&2
    exit 1
  fi
  sleep 2
done

docker exec -i --env MYSQL_PWD="$db_password" "$db_container" \
  mysql --user=frequencia_app frequenciafacil_test < database/schema.sql

docker run -d \
  --name "$app_container" \
  --network "$network" \
  --network-alias app \
  --publish 127.0.0.1:18080:80 \
  --env APP_ENV=ci \
  --env APP_BASE_PATH= \
  --env DB_HOST=db \
  --env DB_PORT=3306 \
  --env MYSQL_DATABASE=frequenciafacil_test \
  --env MYSQL_USER=frequencia_app \
  --env MYSQL_PASSWORD="$db_password" \
  "$image" >/dev/null

for attempt in {1..30}; do
  response="$(curl --fail --silent --show-error http://127.0.0.1:18080/health 2>/dev/null || true)"
  if grep -q '"status":"ok"' <<<"$response" && grep -q '"banco":"conectado"' <<<"$response"; then
    break
  fi
  if [[ "$attempt" == 30 ]]; then
    docker logs "$app_container"
    echo "O endpoint /health nao confirmou a conexao com o MySQL." >&2
    exit 1
  fi
  sleep 2
done

assert_get / 'text/html' 'Fazer a chamada de hoje' 'GET raiz'
assert_get /alunos 'text/html' '<h1>Alunos</h1>' 'GET alunos'
assert_get /chamada 'text/html' '<h1>Chamada</h1>' 'GET chamada'
assert_get /historico 'text/html' '<h1>Hist' 'GET historico'
assert_get /health 'application/json' '"banco":"conectado"' 'GET health'
assert_get /assets/css/estilo.css 'text/css' '.cabecalho' 'GET CSS'
assert_get /assets/js/api.js '(application|text)/javascript' 'export const api' 'GET JavaScript'

if [[ -n "$test_image" ]]; then
  docker run --rm --name "$test_container" \
    --network "$network" \
    --env FF_TEST_BASE_URL=http://app \
    --env DB_HOST=db \
    --env DB_PORT=3306 \
    --env MYSQL_DATABASE=frequenciafacil_test \
    --env MYSQL_USER=frequencia_app \
    --env MYSQL_PASSWORD="$db_password" \
    "$test_image" php vendor/bin/phpunit --filter '(AlunosApiTest|PresencasApiTest|HistoricoApiTest)'
fi

echo "Smoke test HTTP, assets e integracao com MySQL concluido com sucesso."
