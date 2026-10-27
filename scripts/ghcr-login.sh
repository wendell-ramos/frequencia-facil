#!/usr/bin/env bash
set -Eeuo pipefail

[[ "$#" -eq 0 ]] || { echo "Este comando nao aceita argumentos." >&2; exit 2; }
if [[ "$EUID" -ne 0 ]]; then
  echo "Este comando deve ser executado pelo sudo restrito." >&2
  exit 2
fi
config=/etc/frequenciafacil/deploy.conf
test -r "$config" || { echo "Configuracao de deploy ausente." >&2; exit 2; }
# shellcheck source=/dev/null
source "$config"
[[ "${GHCR_USERNAME:-}" =~ ^[A-Za-z0-9_.-]+$ ]] || { echo "GHCR_USERNAME invalido." >&2; exit 2; }

# O token chega apenas pela entrada padrao e nao aparece em argumentos ou logs.
docker login ghcr.io --username "$GHCR_USERNAME" --password-stdin >/dev/null
echo "Autenticacao de leitura no GHCR atualizada."
