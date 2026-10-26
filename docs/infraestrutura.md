# Infraestrutura, CI e CD

## Estrutura adotada

A infraestrutura segue a estrutura incorporada na `main` pelo backend: `index.php`
na raiz, classes em `app/`, schema em `database/schema.sql`, Composer e PHPUnit.
As telas e os assets do frontend tambem fazem parte da imagem de producao.

A imagem usa PHP 8.3 com Apache, `mysqli`, `pdo_mysql` e `mod_rewrite`. O codigo e
copiado para a imagem; producao nao usa bind mount. A rota `/health` executa
`SELECT 1` pelo PDO e e o health check real da aplicacao.

## XAMPP e Docker local

O XAMPP nao depende do Compose. No Windows, a aplicacao deve apontar para o banco
acessivel pelo host:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
```

Dentro do Compose, `DB_HOST=db` funciona porque `db` e o DNS do servico MySQL.
Esse nome nao funciona diretamente no XAMPP. As demais variaveis sao
`MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` e `MYSQL_ROOT_PASSWORD`.

Para Docker local, copie `.env.example` para `.env`, preencha senhas apenas
locais e execute `docker compose up -d --build`. O MySQL nao publica 3306. O
volume `mysql_data` e persistente e recebe o schema somente quando ainda esta
vazio. `docker compose stop` para os servicos sem apagar dados.

Nunca versione `.env`, dumps ou senhas. Em Linux, use permissao `600` no `.env`.
No XAMPP, `APP_BASE_PATH=/frequencia-facil`; nos containers ele e vazio. O
`APP_PORT=8080` preserva o upstream atual do Nginx (`127.0.0.1:8080`).

## Schema e migracoes

`database/schema.sql` descreve a criacao inicial e deve continuar versionado sem
dados pessoais. Ele nao atualiza volumes ja inicializados. Mudancas futuras de
estrutura devem entrar em `database/migrations/` como scripts incrementais,
revisados e acompanhados por backup e procedimento de rollback.

## Compose de producao

O arquivo local continua sendo `compose.yaml`. Na VPS, use os dois arquivos:

```bash
docker compose -f compose.yaml -f compose.prod.yaml config
```

O overlay `compose.prod.yaml` exige:

- `APP_IMAGE=ghcr.io/wendell-ramos/frequencia-facil:sha-<commit>`;
- `MYSQL_VOLUME_NAME` com o nome exato do volume MySQL ja existente.

Em producao, `mysql_data` e externo. O Compose falha se o volume indicado nao
existir, em vez de criar outro silenciosamente. O deploy executa somente `pull
app` e `up -d --no-build --no-deps app`; nao reinicia nem recria o banco.

## CI

`.github/workflows/ci.yml` roda em Pull Requests destinados a `main` ou a
`feature/backend-*` (para revisar entregas dependentes) e em pushes para `main`.
O CD continua restrito a CI bem-sucedido em push na `main`. O CI:

1. valida `composer.json`, instala dependencias e executa `composer test`;
2. valida a configuracao local e o overlay de producao sem segredos reais;
3. constroi a imagem PHP/Apache;
4. executa `php -l` em todos os PHP do repositorio, inclusive testes;
5. confirma `mysqli`, `pdo_mysql` e `mod_rewrite`;
6. inicia MySQL 8.4 em `tmpfs` e exige resposta de sucesso do `/health`.

O `EnvironmentTest` valida o carregamento das variaveis. O smoke test valida
infraestrutura, paginas, assets e conexao PDO. Quando recebe a imagem de testes
como segundo argumento (como no CI), o script aplica `database/schema.sql` ao
MySQL descartavel `frequenciafacil_test` e executa `AlunosApiTest` e
`PresencasApiTest`, `HistoricoApiTest` e `AuthApiTest` contra a
imagem de producao. Essa suite comprova o CRUD de alunos, duplicidade e bloqueio
de exclusao com historico. Tambem testa consulta e gravacao de presencas,
atualizacao sem duplicar e rollback do lote. Historico valida filtros inclusivos,
totais, isolamento entre alunos e periodos sem registros.

As páginas sem sessão agora retornam 401 no smoke test. O teste HTTP de autenticação
confirma que as mesmas páginas continuam retornando HTML após login. Os testes de
negócio recebem cookie/CSRF, e criam/removem somente seus próprios professores de teste.
O comando de provisionamento está em `bin/`, incluído na imagem e bloqueado no Apache.

## CD preparado e desativado

`.github/workflows/cd.yml` usa `workflow_run`: aceita apenas um `push` na `main`
cujo workflow `CI` terminou com sucesso. A variavel de repositorio
`ENABLE_PRODUCTION_CD` deve permanecer ausente ou `false` nesta etapa. O job
tambem usa o environment GitHub `production`, permitindo aprovadores quando o
plano da conta oferecer esse recurso. Deploys sao serializados por concurrency.

Quando habilitado, o workflow constroi a imagem do mesmo SHA aprovado, publica
`ghcr.io/wendell-ramos/frequencia-facil:sha-<commit-completo>`, envia apenas o
script temporario por SSH, atualiza `app`, consulta `/health` e registra
`APP_IMAGE` no `.env`. Se a verificacao falhar, tenta restaurar a imagem anterior.

Secrets do environment `production`:

- `VPS_HOST` e `VPS_USER`;
- `VPS_SSH_PRIVATE_KEY`, uma chave exclusiva e de menor privilegio;
- `VPS_SSH_KNOWN_HOSTS`, conferido fora do pipeline;
- `GHCR_USERNAME` e `GHCR_READ_TOKEN` com somente `read:packages` para pacote privado.

Variaveis do environment `production`:

- `DEPLOY_PATH=/opt/frequenciafacil`;
- `HEALTHCHECK_URL=http://127.0.0.1:8080/health`;
- `VPS_SSH_PORT=22` (opcional).

Permissoes do workflow: `contents: read` e `packages: write`. O repositorio ou a
organizacao precisa permitir ao `GITHUB_TOKEN` publicar pacotes. O usuario remoto
precisa executar Docker sem `sudo` e escrever em `DEPLOY_PATH`. Nao se deve
pressupor que essas permissoes ja existam.

## Preparacao futura da VPS

Esta tarefa nao acessa nem altera a VPS. Antes do primeiro deploy:

1. fazer backup verificado do banco;
2. identificar o volume atual com `docker volume ls` e `docker volume inspect`;
3. revisar e colocar `compose.yaml` e `compose.prod.yaml` em `/opt/frequenciafacil`,
   sem substituir o `.env` atual;
4. adicionar `MYSQL_VOLUME_NAME`, `APP_IMAGE`, `APP_PORT=8080`, `APP_BASE_PATH=` e
   manter `DB_HOST=db`, `DB_PORT=3306` no `.env` com permissao `600`;
5. confirmar que o project name/container atual e o Nginx continuam compativeis;
6. garantir `curl`, acesso ao Docker e autenticacao de leitura no GHCR;
7. executar `docker compose -f compose.yaml -f compose.prod.yaml config` e testar
   manualmente o pull da imagem sem tocar no servico `db`;
8. cadastrar secrets/variables, proteger o environment e ensaiar rollback por tag;
9. aplicar `database/migrations/001_create_professores.sql` no banco existente após backup,
   criar o professor com `bin/criar-professor.php`, configurar HTTPS público e integrar
   a tela de login/CSRF conforme `docs/autenticacao.md`;
10. somente depois definir `ENABLE_PRODUCTION_CD=true`.

Nunca use `docker compose down -v`, `docker volume prune` ou remova o volume de
producao durante deploy da aplicacao.
