#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "$EUID" -ne 0 ]]; then
  echo "Execute como root ou com sudo." >&2
  exit 2
fi

deploy_dir="${FF_DEPLOY_DIR:-/opt/frequenciafacil}"
[[ "$deploy_dir" = /* ]] || { echo "Diretorio de deploy deve ser absoluto." >&2; exit 2; }
backup_dir="$deploy_dir/backups"
schema_file="$deploy_dir/schema.sql"
migration_file="$deploy_dir/migrations/001_create_professores.sql"

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

mode="${1:-}"
if [[ -z "$mode" ]]; then
  echo "Inspecao concluida; nenhuma alteracao foi aplicada."
  exit 0
fi
case "$mode" in
  --initialize-empty|--apply-professores) ;;
  *) echo "Modo invalido. Use --initialize-empty ou --apply-professores." >&2; exit 2 ;;
esac

manifest="${2:?informe o manifesto .sha256 de um backup verificado}"
case "$manifest" in
  "$backup_dir"/*.sql.gz.sha256) ;;
  *) echo "O manifesto deve estar no diretorio protegido de backups." >&2; exit 2 ;;
esac
(cd "$backup_dir" && sha256sum --check "$(basename "$manifest")")

table_count="$("${mysql_exec[@]}" --batch --skip-column-names -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='frequenciafacil'")"
[[ "$table_count" =~ ^[0-9]+$ ]] || { echo "Nao foi possivel contar as tabelas existentes." >&2; exit 1; }

if [[ "$mode" = "--initialize-empty" ]]; then
  if [[ "$table_count" != 0 ]]; then
    echo "Inicializacao recusada: o banco possui $table_count tabela(s)." >&2
    echo "Revise o schema existente e use apenas migracoes incrementais." >&2
    exit 1
  fi
  test -r "$schema_file" || { echo "database/schema.sql instalado nao encontrado." >&2; exit 2; }
  "${mysql_exec[@]}" < "$schema_file"
  actual_tables="$("${mysql_exec[@]}" --batch --skip-column-names -e \
    "SELECT table_name FROM information_schema.tables WHERE table_schema='frequenciafacil' ORDER BY table_name")"
  expected_tables=$'alunos\npresencas\nprofessores'
  if [[ "$actual_tables" != "$expected_tables" ]]; then
    echo "Inicializacao incompleta: conjunto de tabelas inesperado." >&2
    printf 'Esperado:\n%s\nObtido:\n%s\n' "$expected_tables" "$actual_tables" >&2
    exit 1
  fi
  "${mysql_exec[@]}" -e 'SHOW CREATE TABLE alunos; SHOW CREATE TABLE presencas; SHOW CREATE TABLE professores'
  echo "Schema inicial aplicado com seguranca: alunos, presencas e professores."
  exit 0
fi

exists="$("${mysql_exec[@]}" --batch --skip-column-names -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='frequenciafacil' AND table_name='professores'")"
if [[ "$exists" = 1 ]]; then
  echo "A tabela professores ja existe; nenhuma migracao foi aplicada."
  exit 0
fi

"${mysql_exec[@]}" < "$migration_file"
"${mysql_exec[@]}" -e 'SHOW CREATE TABLE professores'
echo "Migracao aditiva de professores aplicada e conferida."
