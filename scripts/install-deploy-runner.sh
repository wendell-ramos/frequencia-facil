#!/usr/bin/env bash
set -Eeuo pipefail

username="${1:?uso: install-deploy-runner.sh GHCR_USERNAME}"
source_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ "$EUID" -ne 0 ]]; then
  echo "Execute como root ou com sudo." >&2
  exit 2
fi
[[ "$username" =~ ^[A-Za-z0-9_.-]+$ ]] || { echo "GHCR_USERNAME invalido." >&2; exit 2; }
id ffdeploy >/dev/null
getent group ssh-users | grep -Eq '(^|,)ffdeploy(,|$)'
command -v docker curl flock visudo sha256sum gzip openssl >/dev/null

deploy_dir=/opt/frequenciafacil
backend_network="${BACKEND_NETWORK_NAME:-frequenciafacil_backend}"
ingress_network="${INGRESS_NETWORK_NAME:-frequenciafacil_ingress}"
app_secret="$deploy_dir/secrets/mysql_app_password"
test -f "$deploy_dir/compose.yaml" || { echo "Compose privado existente nao encontrado." >&2; exit 2; }
test -f "$app_secret" || { echo "Secret MySQL da aplicacao nao encontrado." >&2; exit 2; }
test -f "$deploy_dir/secrets/mysql_root_password" || { echo "Secret MySQL root nao encontrado." >&2; exit 2; }
test "$(stat -c '%u:%g %a' "$app_secret")" = '0:33 640' || {
  echo "Secret MySQL da aplicacao deve ser root:33 com modo 0640." >&2
  exit 2
}
docker network inspect "$backend_network" >/dev/null
docker network inspect "$ingress_network" >/dev/null
test "$(docker network inspect --format '{{.Driver}} {{.Internal}}' "$backend_network")" = 'bridge true' || {
  echo "A rede backend deve usar o driver bridge e internal=true." >&2
  exit 2
}
test "$(docker network inspect --format '{{.Driver}} {{.Internal}}' "$ingress_network")" = 'bridge false' || {
  echo "A rede ingress deve usar o driver bridge e internal=false." >&2
  exit 2
}
docker volume inspect frequenciafacil_mysql_data >/dev/null

# O compose.yaml privado da VPS e os secrets existentes nao sao sobrescritos.
install -o root -g root -m 0644 "$source_dir/compose.prod.yaml" "$deploy_dir/compose.app.yaml"
install -d -o root -g root -m 0700 "$deploy_dir/migrations" "$deploy_dir/backups" "$deploy_dir/.deploy"
install -o root -g root -m 0644 "$source_dir/database/schema.sql" "$deploy_dir/schema.sql"
install -o root -g root -m 0644 "$source_dir/database/migrations/001_create_professores.sql" \
  "$deploy_dir/migrations/001_create_professores.sql"
install -o root -g root -m 0755 "$source_dir/scripts/deploy.sh" /usr/local/sbin/frequenciafacil-deploy
install -o root -g root -m 0755 "$source_dir/scripts/ghcr-login.sh" /usr/local/sbin/frequenciafacil-ghcr-login
install -o root -g root -m 0755 "$source_dir/scripts/backup-database.sh" /usr/local/sbin/frequenciafacil-backup
install -o root -g root -m 0755 "$source_dir/scripts/prepare-database.sh" /usr/local/sbin/frequenciafacil-database

install -d -o root -g root -m 0755 /etc/frequenciafacil
printf 'GHCR_USERNAME=%q\n' "$username" >/etc/frequenciafacil/deploy.conf
chmod 0600 /etc/frequenciafacil/deploy.conf

sudoers_tmp="$(mktemp)"
trap 'rm -f "$sudoers_tmp"' EXIT
cat >"$sudoers_tmp" <<'SUDOERS'
Defaults:ffdeploy env_reset,secure_path=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
ffdeploy ALL=(root) NOPASSWD: /usr/local/sbin/frequenciafacil-ghcr-login
ffdeploy ALL=(root) NOPASSWD: /usr/local/sbin/frequenciafacil-deploy *
SUDOERS
chmod 0440 "$sudoers_tmp"
visudo -cf "$sudoers_tmp"
install -o root -g root -m 0440 "$sudoers_tmp" /etc/sudoers.d/frequenciafacil-deploy

placeholder="ghcr.io/wendell-ramos/frequencia-facil@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
APP_IMAGE="$placeholder" BACKEND_NETWORK_NAME="$backend_network" INGRESS_NETWORK_NAME="$ingress_network" \
  docker compose --project-name frequenciafacil --file "$deploy_dir/compose.app.yaml" config --quiet

echo "Runner restrito instalado. Nenhum container foi criado ou alterado."
sudo -l -U ffdeploy
