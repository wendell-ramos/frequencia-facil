#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "$EUID" -ne 0 ]]; then
  echo "Execute como root ou com sudo." >&2
  exit 2
fi

backup_dir="${FF_BACKUP_DIR:-/opt/frequenciafacil/backups}"
[[ "$backup_dir" = /* ]] || { echo "Diretorio de backup deve ser absoluto." >&2; exit 2; }
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup="$backup_dir/frequenciafacil-$timestamp.sql.gz"
tmp="$backup.tmp"
verify_container="frequenciafacil-backup-verify-$$"

cleanup() {
  rm -f "$tmp"
  docker rm -f "$verify_container" >/dev/null 2>&1 || true
}
trap cleanup EXIT
install -d -o root -g root -m 0700 "$backup_dir"

db_id="$(docker ps -q \
  --filter label=com.docker.compose.project=frequenciafacil \
  --filter label=com.docker.compose.service=db)"
test -n "$db_id" || { echo "Container MySQL nao encontrado." >&2; exit 2; }

# Executa consultas sem copiar a senha para argumentos ou para o host.
# shellcheck disable=SC2016
source_mysql=(docker exec "$db_id" sh -ceu '
  MYSQL_PWD="$(cat /run/secrets/mysql_root_password)"
  export MYSQL_PWD
  exec mysql --batch --skip-column-names --user=root "$@"
' sh)

schema_query="SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='frequenciafacil'"
tables_query="SELECT table_name, table_type FROM information_schema.tables WHERE table_schema='frequenciafacil' ORDER BY table_name, table_type"

source_schema_count="$("${source_mysql[@]}" -e "$schema_query")"
[[ "$source_schema_count" = 1 ]] || { echo "Schema frequenciafacil nao encontrado na origem." >&2; exit 1; }
source_tables="$("${source_mysql[@]}" -e "$tables_query")"
source_table_count="$(sed '/^$/d' <<<"$source_tables" | wc -l | tr -d '[:space:]')"
[[ "$source_table_count" =~ ^[0-9]+$ ]]
echo "Tabelas na origem: $source_table_count"

echo "Criando dump consistente do banco frequenciafacil..."
docker exec "$db_id" sh -ceu '
  MYSQL_PWD="$(cat /run/secrets/mysql_root_password)"
  export MYSQL_PWD
  exec mysqldump --user=root --single-transaction --routines --triggers --events --hex-blob --databases frequenciafacil
' | gzip -9 >"$tmp"
gzip -t "$tmp"
# Literal SQL; nao e uma expansao de shell.
# shellcheck disable=SC2016
gzip -cd "$tmp" | grep 'Current Database: `frequenciafacil`' >/dev/null

# Restaura o dump em MySQL 8.4 descartavel, sem porta, volume persistente ou
# rede compartilhada com producao.
echo "Iniciando restauracao de verificacao em MySQL descartavel..."
verify_password="$(openssl rand -hex 24)"
docker run -d --name "$verify_container" --network none --tmpfs /var/lib/mysql \
  --env MYSQL_ROOT_PASSWORD="$verify_password" mysql:8.4 >/dev/null
verify_ready=false
for _ in {1..90}; do
  if docker logs "$verify_container" 2>&1 | grep -F 'MySQL init process done. Ready for start up.' >/dev/null \
    && docker exec --env MYSQL_PWD="$verify_password" "$verify_container" \
    mysql --batch --skip-column-names --user=root -e 'SELECT 1' >/dev/null 2>&1; then
    verify_ready=true
    break
  fi
  sleep 2
done
if [[ "$verify_ready" != true ]]; then
  docker logs --tail 100 "$verify_container" >&2 || true
  echo "MySQL descartavel nao ficou pronto para validar a restauracao." >&2
  exit 1
fi
echo "Importando o dump no verificador descartavel..."
gzip -cd "$tmp" | docker exec -i --env MYSQL_PWD="$verify_password" "$verify_container" mysql --user=root
restored_schema_count="$(docker exec --env MYSQL_PWD="$verify_password" "$verify_container" \
  mysql --batch --skip-column-names --user=root -e "$schema_query")"
[[ "$restored_schema_count" = 1 ]] || { echo "Schema frequenciafacil nao foi restaurado." >&2; exit 1; }
restored_tables="$(docker exec --env MYSQL_PWD="$verify_password" "$verify_container" \
  mysql --batch --skip-column-names --user=root -e "$tables_query")"
restored_table_count="$(sed '/^$/d' <<<"$restored_tables" | wc -l | tr -d '[:space:]')"
[[ "$restored_table_count" =~ ^[0-9]+$ ]]

if [[ "$restored_tables" != "$source_tables" ]]; then
  echo "As tabelas restauradas diferem da origem." >&2
  diff -u \
    <(printf '%s\n' "$source_tables") \
    <(printf '%s\n' "$restored_tables") >&2 || true
  exit 1
fi

echo "Restauracao verificada; gravando artefato e manifesto protegidos..."
install -o root -g root -m 0600 "$tmp" "$backup"
sha256sum "$backup" >"$backup.sha256"
chmod 0600 "$backup.sha256"

echo "Backup criado e restaurado com sucesso em MySQL descartavel: $backup"
echo "Schema verificado: frequenciafacil"
echo "Tabelas verificadas: $restored_table_count (equivalentes a origem)"
echo "Manifesto SHA-256: $backup.sha256"
