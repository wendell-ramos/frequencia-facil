#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "$EUID" -ne 0 ]]; then
  echo "Execute como root ou com sudo." >&2
  exit 2
fi

db_id="$(docker ps -q \
  --filter label=com.docker.compose.project=frequenciafacil \
  --filter label=com.docker.compose.service=db)"
test -n "$db_id" || { echo "Container MySQL nao encontrado." >&2; exit 2; }

# O script entre aspas simples e interpretado dentro do container.
# shellcheck disable=SC2016
mysql_exec=(docker exec -i "$db_id" sh -ceu '
  MYSQL_PWD="$(cat /run/secrets/mysql_app_password)"
  export MYSQL_PWD
  exec mysql --user=frequencia_app --database=frequenciafacil "$@"
' sh)

echo "Tabelas existentes:"
"${mysql_exec[@]}" --batch --skip-column-names -e 'SHOW TABLES'

if [[ "${1:-}" != "--apply-professores" ]]; then
  echo "Inspecao concluida; nenhuma alteracao foi aplicada."
  exit 0
fi

manifest="${2:?informe o manifesto .sha256 de um backup verificado}"
case "$manifest" in
  /opt/frequenciafacil/backups/*.sql.gz.sha256) ;;
  *) echo "O manifesto deve estar no diretorio protegido de backups." >&2; exit 2 ;;
esac
(cd /opt/frequenciafacil/backups && sha256sum --check "$(basename "$manifest")")

exists="$("${mysql_exec[@]}" --batch --skip-column-names -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='frequenciafacil' AND table_name='professores'")"
if [[ "$exists" = 1 ]]; then
  echo "A tabela professores ja existe; nenhuma migracao foi aplicada."
  exit 0
fi

"${mysql_exec[@]}" < /opt/frequenciafacil/migrations/001_create_professores.sql
"${mysql_exec[@]}" -e 'SHOW CREATE TABLE professores'
echo "Migracao aditiva de professores aplicada e conferida."
