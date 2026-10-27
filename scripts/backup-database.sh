#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "$EUID" -ne 0 ]]; then
  echo "Execute como root ou com sudo." >&2
  exit 2
fi

backup_dir=/opt/frequenciafacil/backups
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
table_count="$(docker exec --env MYSQL_PWD="$verify_password" "$verify_container" \
  mysql --batch --skip-column-names --user=root -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='frequenciafacil'")"
[[ "$table_count" =~ ^[0-9]+$ ]] && (( table_count > 0 ))

echo "Restauracao verificada; gravando artefato e manifesto protegidos..."
install -o root -g root -m 0600 "$tmp" "$backup"
sha256sum "$backup" >"$backup.sha256"
chmod 0600 "$backup.sha256"

echo "Backup criado e restaurado com sucesso em MySQL descartavel: $backup"
echo "Tabelas verificadas: $table_count"
echo "Manifesto SHA-256: $backup.sha256"
