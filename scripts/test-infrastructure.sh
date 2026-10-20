#!/usr/bin/env bash
set -Eeuo pipefail

image="${1:?uso: test-infrastructure.sh IMAGEM}"
suffix="${GITHUB_RUN_ID:-local}-$$"
network="frequenciafacil-ci-$suffix"
db_container="frequenciafacil-ci-db-$suffix"
app_container="frequenciafacil-ci-app-$suffix"
db_password='ci-app-password'
root_password='ci-root-password'

# Invocada indiretamente pelo trap.
# shellcheck disable=SC2329
cleanup() {
  docker rm -f "$app_container" "$db_container" >/dev/null 2>&1 || true
  docker network rm "$network" >/dev/null 2>&1 || true
}
trap cleanup EXIT

modules="$(docker run --rm "$image" php -m)"
grep -qx 'mysqli' <<<"$modules"
grep -qx 'pdo_mysql' <<<"$modules"

apache_modules="$(docker run --rm "$image" apache2ctl -M 2>/dev/null)"
grep -q 'rewrite_module' <<<"$apache_modules"

docker network create "$network" >/dev/null
docker run -d \
  --name "$db_container" \
  --network "$network" \
  --network-alias db \
  --tmpfs /var/lib/mysql \
  --env MYSQL_DATABASE=frequenciafacil \
  --env MYSQL_USER=frequencia_app \
  --env MYSQL_PASSWORD="$db_password" \
  --env MYSQL_ROOT_PASSWORD="$root_password" \
  mysql:8.4 >/dev/null

for attempt in {1..40}; do
  if docker exec --env MYSQL_PWD="$db_password" "$db_container" \
    mysqladmin ping --host=127.0.0.1 --user=frequencia_app --silent >/dev/null 2>&1; then
    break
  fi
  if [[ "$attempt" == 40 ]]; then
    docker logs "$db_container"
    echo "MySQL nao ficou pronto para o smoke test." >&2
    exit 1
  fi
  sleep 2
done

docker run -d \
  --name "$app_container" \
  --network "$network" \
  --publish 127.0.0.1:18080:80 \
  --env APP_ENV=ci \
  --env APP_BASE_PATH= \
  --env DB_HOST=db \
  --env DB_PORT=3306 \
  --env MYSQL_DATABASE=frequenciafacil \
  --env MYSQL_USER=frequencia_app \
  --env MYSQL_PASSWORD="$db_password" \
  "$image" >/dev/null

for attempt in {1..30}; do
  response="$(curl --fail --silent --show-error http://127.0.0.1:18080/health 2>/dev/null || true)"
  if grep -q '"status":"ok"' <<<"$response" && grep -q '"banco":"conectado"' <<<"$response"; then
    echo "Smoke test de /health com MySQL concluido com sucesso."
    exit 0
  fi
  sleep 2
done

docker logs "$app_container"
echo "O endpoint /health nao confirmou a conexao com o MySQL." >&2
exit 1
