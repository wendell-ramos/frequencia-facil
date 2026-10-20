#!/usr/bin/env bash
set -Eeuo pipefail

image="${1:?uso: deploy.sh IMAGEM DEPLOY_DIR [HEALTHCHECK_URL]}"
deploy_dir="${2:?uso: deploy.sh IMAGEM DEPLOY_DIR [HEALTHCHECK_URL]}"
healthcheck_url="${3:-http://127.0.0.1:8080/health}"

case "$image" in
  ghcr.io/*@sha256:*|ghcr.io/*:sha-*) ;;
  *) echo "A imagem deve ser do GHCR e usar digest ou tag sha-." >&2; exit 2 ;;
esac

case "$deploy_dir" in
  /*) ;;
  *) echo "DEPLOY_DIR deve ser um caminho absoluto." >&2; exit 2 ;;
esac

cd "$deploy_dir"
test -f compose.yaml || { echo "compose.yaml ausente em $deploy_dir" >&2; exit 2; }
test -f compose.prod.yaml || { echo "compose.prod.yaml ausente em $deploy_dir" >&2; exit 2; }
test -f .env || { echo ".env ausente em $deploy_dir" >&2; exit 2; }

volume_name="$(sed -n 's/^MYSQL_VOLUME_NAME=//p' .env | tail -n 1 | tr -d '\r' | tr -d '\"' | tr -d "'")"
test -n "$volume_name" || { echo "MYSQL_VOLUME_NAME deve apontar para o volume existente." >&2; exit 2; }
case "$volume_name" in
  *[!a-zA-Z0-9_.-]*) echo "MYSQL_VOLUME_NAME invalido." >&2; exit 2 ;;
esac
docker volume inspect "$volume_name" >/dev/null

compose=(docker compose -f compose.yaml -f compose.prod.yaml)
APP_IMAGE="$image" "${compose[@]}" config --quiet
current_container="$(APP_IMAGE="$image" "${compose[@]}" ps -q app 2>/dev/null || true)"
previous_image=""
if [ -n "$current_container" ]; then
  previous_image="$(docker inspect --format '{{.Config.Image}}' "$current_container")"
fi

APP_IMAGE="$image" "${compose[@]}" pull app
APP_IMAGE="$image" "${compose[@]}" up -d --no-build --no-deps app

healthy=false
for attempt in {1..30}; do
  if curl --fail --silent --show-error "$healthcheck_url" >/dev/null; then
    healthy=true
    break
  fi
  if [ "$attempt" -lt 30 ]; then
    sleep 2
  fi
done

if [ "$healthy" != true ]; then
  echo "Health check falhou para $image." >&2
  if [ -n "$previous_image" ] && [ "$previous_image" != "$image" ]; then
    echo "Restaurando a imagem anterior da aplicacao: $previous_image" >&2
    APP_IMAGE="$previous_image" "${compose[@]}" up -d --no-build --no-deps app
  fi
  exit 1
fi

tmp_env="$(mktemp "$deploy_dir/.env.deploy.XXXXXX")"
awk -v image="$image" '
  BEGIN { updated = 0 }
  /^APP_IMAGE=/ { print "APP_IMAGE=" image; updated = 1; next }
  { print }
  END { if (!updated) print "APP_IMAGE=" image }
' .env >"$tmp_env"
chmod --reference=.env "$tmp_env"
mv "$tmp_env" .env

echo "Aplicacao atualizada para $image; o servico db nao foi recriado."
