#!/usr/bin/env bash
set -Eeuo pipefail

# Instalado como /usr/local/sbin/frequenciafacil-deploy, root:root e nao gravavel
# por ffdeploy. Aceita somente um digest oficial e o SHA Git validado pelo CI.
[[ "$#" -eq 2 ]] || { echo "uso: frequenciafacil-deploy IMAGEM@sha256:DIGEST COMMIT_SHA" >&2; exit 2; }
image="$1"
commit_sha="$2"
deploy_dir=/opt/frequenciafacil
compose_file="$deploy_dir/compose.app.yaml"
healthcheck_url=http://127.0.0.1:8080/health
expected_repository=ghcr.io/wendell-ramos/frequencia-facil

if [[ "$EUID" -ne 0 ]]; then
  echo "Este comando deve ser executado pelo sudo restrito." >&2
  exit 2
fi
if [[ ! "$image" =~ ^${expected_repository}@sha256:[a-f0-9]{64}$ ]]; then
  echo "Imagem recusada: use o digest imutavel do repositorio oficial." >&2
  exit 2
fi
[[ "$commit_sha" =~ ^[a-f0-9]{40}$ ]] || { echo "Commit SHA invalido." >&2; exit 2; }

exec 9>/run/lock/frequenciafacil-deploy.lock
flock -n 9 || { echo "Ja existe um deploy em andamento." >&2; exit 75; }

test -f "$compose_file" || { echo "Compose da aplicacao ausente." >&2; exit 2; }
test -r "$deploy_dir/secrets/mysql_app_password" || { echo "Secret da aplicacao ausente ou ilegivel." >&2; exit 2; }
docker network inspect frequenciafacil_backend >/dev/null
docker volume inspect frequenciafacil_mysql_data >/dev/null

db_id="$(docker ps -q \
  --filter label=com.docker.compose.project=frequenciafacil \
  --filter label=com.docker.compose.service=db)"
test -n "$db_id" || { echo "Container MySQL do projeto nao encontrado." >&2; exit 2; }
test "$(docker inspect --format '{{.State.Running}}' "$db_id")" = true
docker inspect --format '{{range .Mounts}}{{println .Name "|" .Destination}}{{end}}' "$db_id" \
  | grep -Fx 'frequenciafacil_mysql_data | /var/lib/mysql' >/dev/null

compose=(docker compose --project-name frequenciafacil --file "$compose_file")
APP_IMAGE="$image" "${compose[@]}" config --quiet

app_id="$(APP_IMAGE="$image" "${compose[@]}" ps -q app 2>/dev/null || true)"
previous_image=""
if [[ -n "$app_id" ]]; then
  previous_image="$(docker inspect --format '{{.Config.Image}}' "$app_id")"
fi

health_ok() {
  local response
  response="$(curl --fail --silent --show-error --max-time 5 "$healthcheck_url" 2>/dev/null || true)"
  grep -q '"status":"ok"' <<<"$response" && grep -q '"banco":"conectado"' <<<"$response"
}

rollback() {
  if [[ -z "$previous_image" || "$previous_image" = "$image" ]]; then
    echo "Rollback indisponivel: nao ha imagem anterior distinta." >&2
    return 1
  fi
  echo "Restaurando a imagem anterior da aplicacao: $previous_image" >&2
  if ! APP_IMAGE="$previous_image" "${compose[@]}" up -d --no-build --no-deps app; then
    echo "Rollback falhou ao recriar o servico app." >&2
    return 1
  fi
  for _ in {1..30}; do
    if health_ok; then
      echo "Rollback confirmado pelo healthcheck." >&2
      return 0
    fi
    sleep 2
  done
  echo "Rollback executado, mas a imagem anterior nao passou no healthcheck." >&2
  return 1
}

if ! docker pull "$image"; then
  echo "Pull falhou; o servico atual nao foi alterado." >&2
  exit 1
fi
image_revision="$(docker image inspect --format '{{index .Config.Labels "org.opencontainers.image.revision"}}' "$image")"
if [[ "$image_revision" != "$commit_sha" ]]; then
  echo "Imagem recusada: o label de revisao nao corresponde ao commit aprovado." >&2
  exit 1
fi
if ! APP_IMAGE="$image" "${compose[@]}" up -d --no-build --no-deps app; then
  echo "Falha ao atualizar o servico app." >&2
  rollback || true
  exit 1
fi

healthy=false
for _ in {1..30}; do
  if health_ok; then
    healthy=true
    break
  fi
  sleep 2
done
if [[ "$healthy" != true ]]; then
  echo "Healthcheck falhou para a nova imagem." >&2
  rollback || true
  exit 1
fi

db_id_after="$(docker ps -q \
  --filter label=com.docker.compose.project=frequenciafacil \
  --filter label=com.docker.compose.service=db)"
if [[ "$db_id_after" != "$db_id" ]]; then
  echo "Verificacao de seguranca falhou: a identidade do MySQL mudou durante o deploy." >&2
  rollback || true
  exit 1
fi

install -d -m 700 "$deploy_dir/.deploy"
printf '%s\n' "$image" >"$deploy_dir/.deploy/current-image"
chmod 600 "$deploy_dir/.deploy/current-image"
echo "Aplicacao atualizada para $image; MySQL preservado ($db_id)."
