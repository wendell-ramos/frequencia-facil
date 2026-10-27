# Infraestrutura, CI e CD

## Ambientes

A imagem usa PHP 8.3, Apache, `mysqli`, `pdo_mysql` e `mod_rewrite`. O código,
frontend e vendor são copiados para a imagem; produção não usa bind mount. O
endpoint `/health` executa consultas reais via PDO e exige também a tabela
`professores`, evitando aprovar uma versão sem a migração de autenticação.

No XAMPP use `DB_HOST=127.0.0.1`; dentro do Docker use `DB_HOST=db`. Para Docker
local, copie `.env.example` para `.env`, use apenas credenciais locais e execute:

```bash
docker compose up -d --build
```

O Compose local cria o MySQL para desenvolvimento, não publica 3306 e aplica
`database/schema.sql` somente quando o volume está vazio. Nunca versionar `.env`,
dumps ou senhas e nunca usar `down -v` em produção.

## Produção e MySQL existente

`compose.prod.yaml` é deliberadamente independente do Compose local. Ele define
somente `app`, publica `127.0.0.1:8080`, conecta-se à rede externa
`frequenciafacil_backend` e lê a credencial da aplicação em
`/run/secrets/mysql_app_password`. Ele não declara serviço ou volume MySQL e não
recebe a senha root.

Na VPS o arquivo é instalado como `/opt/frequenciafacil/compose.app.yaml`. O
`compose.yaml`, `compose.security.yaml`, secrets, rede e volume privados já existentes
permanecem inalterados. O único comando de atualização é equivalente a:

```bash
APP_IMAGE=ghcr.io/wendell-ramos/frequencia-facil@sha256:<digest> \
  docker compose --project-name frequenciafacil \
  --file /opt/frequenciafacil/compose.app.yaml \
  up -d --no-build --no-deps app
```

O comando real fica no wrapper root `/usr/local/sbin/frequenciafacil-deploy`,
valida repositório/digest e o label do SHA aprovado, serializa execuções, faz
healthcheck e confere que o ID
do container MySQL não mudou. `ffdeploy` não entra no grupo Docker e não pode editar
o wrapper; sudoers autoriza somente o login GHCR e esse deploy validado.

## CI

`.github/workflows/ci.yml` roda em PRs para `main`/`feature/backend-*` e pushes em
`main`. Ele valida Composer, Compose local e produção, builds `production`/`test`,
PHPUnit, lint PHP, sintaxe JavaScript, sintaxe/ShellCheck dos scripts e um MySQL 8.4
descartável.

O smoke verifica `/health`, `/login`, assets e respostas anônimas. Os testes HTTP
criam professor aleatório apenas em `frequenciafacil_test`, exercitam login/logout,
cookie, CSRF, bloqueio de tentativas, autorização, CRUD de alunos, presença e
histórico, e removem seus próprios dados de teste. Não há credencial padrão.

## CD desativado e primeiro deploy

O CD aceita automaticamente apenas o `workflow_run` de um `push` em `main` com CI
aprovado e `ENABLE_PRODUCTION_CD=true`. O workflow reconfirma que o SHA ainda é a
ponta da `main`, publica a tag `sha-<commit>` e implanta o digest imutável retornado
pelo GHCR. Deploys são serializados.

Enquanto a variável estiver `false`, o primeiro deploy só pode ocorrer por
`workflow_dispatch`, informando o SHA atual com CI de push aprovado e a confirmação
`DEPLOY-CONTROLADO`. Isso não ativa execuções futuras. PRs nunca publicam produção.

O CD não executa SQL. Backup, restauração de verificação, migração aditiva e primeira
conta são etapas administrativas separadas. HTTPS é obrigatório para o cookie Secure.

O estado auditado da VPS, comandos administrativos, GitHub Secrets/Variables,
backup, Nginx, Netdata, primeiro deploy e checklist completo estão em
[`DEPLOY_VPS.md`](DEPLOY_VPS.md).
