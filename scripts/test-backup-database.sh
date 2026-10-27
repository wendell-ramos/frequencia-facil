#!/usr/bin/env bash
set -Eeuo pipefail

suffix="${GITHUB_RUN_ID:-local}-$$"
db_container="frequenciafacil-backup-source-$suffix"
secret_volume="frequenciafacil-backup-secrets-$suffix"
deploy_dir="$(mktemp -d)"
backup_dir="$deploy_dir/backups"
app_password=ci-app-password
root_password=ci-root-password

as_root() {
  if [[ "$EUID" -eq 0 ]]; then
    "$@"
  else
    sudo "$@"
  fi
}

cleanup() {
  docker rm -f "$db_container" >/dev/null 2>&1 || true
  docker volume rm "$secret_volume" >/dev/null 2>&1 || true
  as_root rm -rf -- "$deploy_dir"
}
trap cleanup EXIT

mkdir -p "$backup_dir" "$deploy_dir/migrations"
cp database/schema.sql "$deploy_dir/schema.sql"
cp database/migrations/001_create_professores.sql "$deploy_dir/migrations/001_create_professores.sql"

docker volume create "$secret_volume" >/dev/null
docker run --rm --volume "$secret_volume:/secrets" alpine:3.22 sh -ceu '
  printf %s "$1" > /secrets/mysql_app_password
  printf %s "$2" > /secrets/mysql_root_password
  chmod 0400 /secrets/mysql_app_password /secrets/mysql_root_password
' sh "$app_password" "$root_password"

docker run -d \
  --name "$db_container" \
  --label com.docker.compose.project=frequenciafacil \
  --label com.docker.compose.service=db \
  --tmpfs /var/lib/mysql \
  --volume "$secret_volume:/run/secrets:ro" \
  --env MYSQL_DATABASE=frequenciafacil \
  --env MYSQL_USER=frequencia_app \
  --env MYSQL_PASSWORD_FILE=/run/secrets/mysql_app_password \
  --env MYSQL_ROOT_PASSWORD_FILE=/run/secrets/mysql_root_password \
  mysql:8.4 >/dev/null

for attempt in {1..90}; do
  if docker exec --env MYSQL_PWD="$app_password" "$db_container" \
    mysql --batch --skip-column-names --user=frequencia_app --database=frequenciafacil \
    -e 'SELECT 1' >/dev/null 2>&1; then
    break
  fi
  if [[ "$attempt" = 90 ]]; then
    docker logs "$db_container"
    echo "MySQL vazio nao ficou pronto para o teste de backup." >&2
    exit 1
  fi
  sleep 2
done

empty_output="$(as_root env FF_BACKUP_DIR="$backup_dir" bash scripts/backup-database.sh)"
grep -Fq 'Tabelas na origem: 0' <<<"$empty_output"
grep -Fq 'Tabelas verificadas: 0 (equivalentes a origem)' <<<"$empty_output"
empty_manifest="$(as_root find "$backup_dir" -maxdepth 1 -type f -name '*.sql.gz.sha256' -print)"
[[ -n "$empty_manifest" ]]

as_root env FF_DEPLOY_DIR="$deploy_dir" bash scripts/prepare-database.sh \
  --initialize-empty "$empty_manifest" >/dev/null
table_count="$(docker exec --env MYSQL_PWD="$app_password" "$db_container" \
  mysql --batch --skip-column-names --user=frequencia_app --database=frequenciafacil \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='frequenciafacil'")"
[[ "$table_count" = 3 ]]

if as_root env FF_DEPLOY_DIR="$deploy_dir" bash scripts/prepare-database.sh \
  --initialize-empty "$empty_manifest" >/dev/null 2>&1; then
  echo "A reinicializacao de banco nao vazio deveria ter sido recusada." >&2
  exit 1
fi

sleep 1
populated_output="$(as_root env FF_BACKUP_DIR="$backup_dir" bash scripts/backup-database.sh)"
grep -Fq 'Tabelas na origem: 3' <<<"$populated_output"
grep -Fq 'Tabelas verificadas: 3 (equivalentes a origem)' <<<"$populated_output"
backup_count="$(as_root find "$backup_dir" -maxdepth 1 -type f -name '*.sql.gz' -printf '.' | wc -c)"
[[ "$backup_count" = 2 ]]

echo "Backups vazio e populado, equivalencia e inicializacao segura validados."
