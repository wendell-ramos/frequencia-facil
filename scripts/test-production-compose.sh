#!/usr/bin/env bash
set -Eeuo pipefail

image="${1:?uso: test-production-compose.sh IMAGEM}"
suffix="${GITHUB_RUN_ID:-local}-$$"
project="frequenciafacil-prod-ci-$suffix"
backend_network="frequenciafacil-backend-ci-$suffix"
ingress_network="frequenciafacil-ingress-ci-$suffix"
db_container="frequenciafacil-prod-db-ci-$suffix"
app_port=18081
secret_dir="$(mktemp -d .tmp-prod-compose.XXXXXX)"
secret_file="$secret_dir/mysql_app_password"
secret_volume=""
override_file="$secret_dir/compose.windows.yaml"
db_password='ci-app-password'
root_password='ci-root-password'
compose=(docker compose --project-name "$project" --file compose.prod.yaml)

# shellcheck disable=SC2329
cleanup() {
  APP_IMAGE="$image" APP_PORT="$app_port" \
    BACKEND_NETWORK_NAME="$backend_network" INGRESS_NETWORK_NAME="$ingress_network" \
    MYSQL_APP_PASSWORD_FILE="$secret_file" \
    "${compose[@]}" rm --stop --force app >/dev/null 2>&1 || true
  docker rm -f "$db_container" >/dev/null 2>&1 || true
  docker network rm "$ingress_network" "$backend_network" >/dev/null 2>&1 || true
  if [[ -n "$secret_volume" ]]; then
    docker volume rm "$secret_volume" >/dev/null 2>&1 || true
  fi
  rm -rf "$secret_dir"
}
trap cleanup EXIT

if grep -R --line-number --fixed-strings -- '--remove-orphans' scripts/deploy.sh scripts/install-deploy-runner.sh; then
  echo "Deploy e instalador nao podem usar --remove-orphans." >&2
  exit 1
fi

docker network create --internal "$backend_network" >/dev/null
docker network create --driver bridge "$ingress_network" >/dev/null
test "$(docker network inspect --format '{{.Driver}} {{.Internal}}' "$backend_network")" = 'bridge true'
test "$(docker network inspect --format '{{.Driver}} {{.Internal}}' "$ingress_network")" = 'bridge false'

if [[ "$(uname -s)" == MINGW* ]]; then
  secret_volume="$project-secret"
  docker volume create "$secret_volume" >/dev/null
  docker run --rm --volume "$secret_volume:/secrets" alpine:3.22 sh -eu -c \
    "printf '%s\\n' '$db_password' > /secrets/mysql_app_password; chown 0:33 /secrets/mysql_app_password; chmod 0640 /secrets/mysql_app_password"
  printf '%s\n' "$db_password" >"$secret_file"
  cat >"$override_file" <<YAML
services:
  app:
    secrets: !reset []
    volumes:
      - type: volume
        source: mysql_app_password_dir
        target: /run/secrets
        read_only: true
volumes:
  mysql_app_password_dir:
    external: true
    name: $secret_volume
YAML
  compose+=(--file "$override_file")
else
  docker run --rm --volume "$PWD/$secret_dir:/secrets" alpine:3.22 sh -eu -c \
    "printf '%s\\n' '$db_password' > /secrets/mysql_app_password; chown 0:33 /secrets/mysql_app_password; chmod 0640 /secrets/mysql_app_password"
fi

MSYS_NO_PATHCONV=1 docker run -d \
  --name "$db_container" \
  --network "$backend_network" \
  --network-alias db \
  --tmpfs /var/lib/mysql \
  --env MYSQL_DATABASE=frequenciafacil \
  --env MYSQL_USER=frequencia_app \
  --env MYSQL_PASSWORD="$db_password" \
  --env MYSQL_ROOT_PASSWORD="$root_password" \
  mysql:8.4 >/dev/null

for attempt in {1..90}; do
  if docker exec --env MYSQL_PWD="$db_password" "$db_container" \
    mysql --protocol=TCP --host=127.0.0.1 --user=frequencia_app frequenciafacil -e 'SELECT 1' >/dev/null 2>&1; then
    break
  fi
  if [[ "$attempt" == 90 ]]; then
    docker logs "$db_container"
    echo "MySQL nao ficou pronto para o teste do Compose de producao." >&2
    exit 1
  fi
  sleep 2
done

docker exec -i --env MYSQL_PWD="$db_password" "$db_container" \
  mysql --user=frequencia_app frequenciafacil < database/schema.sql

APP_IMAGE="$image" APP_PORT="$app_port" \
  BACKEND_NETWORK_NAME="$backend_network" INGRESS_NETWORK_NAME="$ingress_network" \
  MYSQL_APP_PASSWORD_FILE="$secret_file" \
  "${compose[@]}" up -d --no-build --no-deps app

app_id="$(APP_IMAGE="$image" APP_PORT="$app_port" \
  BACKEND_NETWORK_NAME="$backend_network" INGRESS_NETWORK_NAME="$ingress_network" \
  MYSQL_APP_PASSWORD_FILE="$secret_file" "${compose[@]}" ps -q app)"
test -n "$app_id"

mapfile -t app_networks < <(docker inspect --format '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' "$app_id" \
  | tr -d '\r' | sed '/^$/d' | sort)
expected_networks=("$backend_network" "$ingress_network")
[[ "${app_networks[*]}" == "${expected_networks[*]}" ]]
test "$(docker inspect --format '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' "$db_container" \
  | tr -d '\r' | sed '/^$/d')" = "$backend_network"
test "$(docker port "$app_id" 80/tcp)" = "127.0.0.1:$app_port"
if ! MSYS_NO_PATHCONV=1 docker exec --user 33:33 "$app_id" test -r /run/secrets/mysql_app_password; then
  docker inspect --format '{{json .Mounts}}' "$app_id" >&2
  MSYS_NO_PATHCONV=1 docker exec "$app_id" ls -ldn /run/secrets /run/secrets/mysql_app_password >&2 || true
  echo "O processo PHP (33:33) nao consegue ler o secret da aplicacao." >&2
  exit 1
fi
test "$(MSYS_NO_PATHCONV=1 docker exec "$app_id" stat -c '%u:%g %a' /run/secrets/mysql_app_password)" = '0:33 640'

for attempt in {1..30}; do
  response="$(curl --fail --silent --show-error "http://127.0.0.1:$app_port/health" 2>/dev/null || true)"
  if grep -q '"status":"ok"' <<<"$response" && grep -q '"banco":"conectado"' <<<"$response"; then
    break
  fi
  if [[ "$attempt" == 30 ]]; then
    docker logs "$app_id"
    echo "A porta publicada e o /health nao ficaram disponiveis." >&2
    exit 1
  fi
  sleep 2
done

echo "Compose de producao validado: app em backend+ingress, MySQL somente backend, porta loopback e health OK."
