# Deploy do FrequênciaFácil na VPS

## 1. Estado confirmado em 28/10/2026

A `main` de partida desta correção está em
`06023423a61da4515721bf44a726d9d07539ebde` (merge do PR #12). O CD automático
continua desativado e esta alteração não acessa nem modifica a VPS.

A VPS foi consultada por SSH, somente leitura, com a chave dedicada:

- hostname `vpsTVGDW`, Ubuntu/kernel `6.8.0-147-generic`;
- fingerprint ED25519 confirmada no próprio servidor:
  `SHA256:Yiq2+MhvHalcJv1no5MbvJmyB67IgtvPluA/Raiti9A`;
- swap de 2 GiB ativa;
- `ffdeploy` pertence a `ssh-users`, mas não possui sudo nem acesso ao Docker;
- `/opt/frequenciafacil` e os dois Compose pertencem a root; `secrets/` está em
  modo `0700` e não foi lido;
- Docker Compose v5.6.1; `daemon.json` confirma `json-file`, 10 MiB, três arquivos
  e `no-new-privileges`;
- Nginx atende HTTP em todas as interfaces e encaminha para `127.0.0.1:8080`;
- não há TLS/certificado configurado nem `certbot` instalado;
- MySQL não aparece em nenhuma porta do host na visão sem privilégios;
- Netdata está ativo e a porta 19999 escuta em `0.0.0.0` e `[::]`. Isso deve ser
  corrigido antes de expor produção.

Em inspeção administrativa informada pelo responsável, o MySQL
`frequenciafacil-db-1` e o PHP `frequenciafacil-app-1` estavam healthy e `/health`
retornava HTTP 200 com banco conectado. A rede `frequenciafacil_backend` é interna;
a rede bridge externa `frequenciafacil_ingress` já existe. A publicação
`127.0.0.1:8080` passou a funcionar somente depois de conectar o app manualmente à
ingress. O Nginx permanece em manutenção, retornando 503. Nenhum arquivo, container,
rede, volume ou segredo da VPS foi alterado por esta correção.

## 2. Problemas encontrados e correções do repositório

O antigo `compose.prod.yaml` era um overlay do Compose local. Por isso ainda
interpolava `MYSQL_PASSWORD`/`MYSQL_ROOT_PASSWORD` e incluía o serviço `db`, o que
não corresponde à VPS. Ele agora é independente e contém somente `app`, usa a rede
externa `frequenciafacil_backend` para falar com o MySQL e a rede bridge externa
`frequenciafacil_ingress` para publicar Apache em `127.0.0.1:8080`. Somente o app
participa das duas redes; o MySQL permanece exclusivamente na backend. O Compose
marca ambas como `external: true`, portanto não cria nem recria nenhuma delas. O
secret de root nunca chega à aplicação.

`Database.php` aceita `MYSQL_PASSWORD_FILE`, recusa arquivo ausente/symlink e mantém
`MYSQL_PASSWORD` apenas para desenvolvimento/testes locais. Há testes unitários para
precedência, leitura e falha segura do secret.

O CD agora publica a tag rastreável `sha-<commit>` e implanta pela referência imutável
`ghcr.io/wendell-ramos/frequencia-facil@sha256:<digest>`. Antes de construir, confirma
que o SHA aprovado ainda é a ponta atual da `main`. Um disparo manual exige o SHA da
`main`, CI de `push` aprovado e a frase `DEPLOY-CONTROLADO`; o caminho automático só
é aceito quando a variável de repositório `ENABLE_PRODUCTION_CD` vale exatamente
`true`.

O workflow não envia scripts executáveis a cada execução e não dá Docker a
`ffdeploy`. O administrador instala wrappers root imutáveis e sudoers limitado. O
deploy usa `flock`, confere o label OCI do SHA aprovado, faz pull antes de alterar o serviço, executa apenas
`up -d --no-build --no-deps app`, exige health JSON conectado ao banco, confere que
o ID do MySQL não mudou e registra o digest atual. Deploy e rollback não usam
`--remove-orphans`. Em falha após a troca, tenta a
imagem anterior e só informa rollback confirmado se ela também passar no healthcheck.

Backups e mudanças de schema permanecem fora do CD. O backup lógico usa transação
consistente, SHA-256, `gzip -t` e restauração integral em MySQL 8.4 descartável. Ele
exige que o schema exista e compara a lista ordenada de nomes/tipos de tabelas entre
origem e restauração, inclusive quando ambas possuem zero tabelas. Banco comprovadamente
vazio pode receber o `database/schema.sql` completo; banco parcial usa somente migrações
incrementais, sempre após validar o manifesto do backup.

## 3. Arquivos alterados ou adicionados

- `.github/workflows/ci.yml`: Compose local/produção separados, shellcheck e smoke ampliado.
- `.github/workflows/cd.yml`: gate manual/automático, digest, GHCR e SSH restrito.
- `compose.prod.yaml`: serviço `app` isolado e compatível com os secrets da VPS.
- `app/Config/Database.php` e `tests/DatabaseConfigTest.php`: credencial por arquivo.
- `scripts/deploy.sh`: deploy/healthcheck/rollback preservando o MySQL.
- `scripts/ghcr-login.sh`: token do GHCR somente pela entrada padrão.
- `scripts/install-deploy-runner.sh`: instalação root auditável sem tocar em containers.
- `scripts/test-production-compose.sh`: duas redes descartáveis, publicação em loopback,
  permissão do secret e healthcheck real sem incluir o MySQL no Compose de produção.
- `scripts/backup-database.sh`: backup, hash e ensaio de restauração descartável.
- `scripts/prepare-database.sh`: inspeção, inicialização vazia ou migração condicionada a backup.
- `scripts/test-backup-database.sh`: ensaio de backup vazio e populado em MySQL descartável.
- `scripts/test-infrastructure.sh`: `/login` e JavaScript de login no smoke.
- `ops/nginx/*`: modo de manutenção e modelo HTTPS/rate limit.
- `.env.example` e este documento.

## 4. Validações desta correção

As validações locais abaixo usaram somente Docker Desktop e recursos descartáveis;
nenhum dado ou serviço da VPS foi acessado:

- `docker compose -f compose.prod.yaml config --quiet`: passou;
- build do target `production`: passou;
- `scripts/test-production-compose.sh`: passou com backend `bridge internal=true`,
  ingress `bridge internal=false`, app nas duas redes, MySQL somente na backend,
  publicação `127.0.0.1:18081`, secret `root:33 0640` legível por `33:33` e
  `/health` HTTP 200 com banco conectado;
- Bash `-n` de todos os scripts, ShellCheck dos três scripts alterados, Actionlint e
  `git diff --check`: passaram sem achados;
- o teste recusa `--remove-orphans` em instalador/deploy e usa apenas
  `up -d --no-build --no-deps app` para criar a aplicação descartável.

O CI do PR repete esse teste em Linux, além da suíte PHPUnit, integração MySQL,
smoke HTTP, backup/restauração, lints e builds já existentes. Runner restrito,
backup real, deploy, logs e rollback continuam sem execução na VPS.

## 5. Preparação administrativa da VPS

Não executar estes comandos antes de revisar o diff/PR. Transfira somente os arquivos
versionados; nunca copie a pasta local `.git`, `.env` ou qualquer chave:

```powershell
ssh -i $env:USERPROFILE\.ssh\ff_actions_ed25519 ffdeploy@164.163.11.240 `
  "install -d -m 700 /tmp/frequenciafacil-release"
scp -i $env:USERPROFILE\.ssh\ff_actions_ed25519 -r `
  compose.prod.yaml database ops scripts `
  ffdeploy@164.163.11.240:/tmp/frequenciafacil-release
```

Em uma sessão administrativa já autorizada na VPS, confira os alvos e instale. O
argumento abaixo é o usuário do pacote GHCR, não uma senha:

```bash
sudo find /tmp/frequenciafacil-release -maxdepth 3 -type f -printf '%m %u:%g %p\n'
sudo bash /tmp/frequenciafacil-release/scripts/install-deploy-runner.sh wendell-ramos
sudo visudo -cf /etc/sudoers.d/frequenciafacil-deploy
sudo -l -U ffdeploy
```

O instalador preserva `/opt/frequenciafacil/compose.yaml`,
`compose.security.yaml`, `secrets/` e o volume. Ele instala somente
`compose.app.yaml`, `schema.sql`, migration, wrappers e sudoers. Não cria/recria containers.

Antes de executá-lo, confira as duas redes existentes. A ingress deve ser bridge e
não interna; o instalador recusa qualquer outra configuração e nunca tenta recriá-la:

```bash
sudo docker network inspect frequenciafacil_backend \
  --format 'backend driver={{.Driver}} internal={{.Internal}}'
sudo docker network inspect frequenciafacil_ingress \
  --format 'ingress driver={{.Driver}} internal={{.Internal}}'
# esperado para ingress: driver=bridge internal=false
```

O secret da aplicação deve continuar protegido, mas legível pelo processo PHP
`www-data` (GID 33). Estes comandos alteram somente metadados, não exibem nem
substituem a senha:

```bash
sudo chown root:33 /opt/frequenciafacil/secrets/mysql_app_password
sudo chmod 0640 /opt/frequenciafacil/secrets/mysql_app_password
sudo stat -c '%U:%G %a %n' /opt/frequenciafacil/secrets/mysql_app_password
# esperado: root:www-data 640
```

Não use `0644`, não passe a senha por argumento ou variável de ambiente e não
altere o secret `mysql_root_password` para atender ao app.

### Atualizar a instalação depois do merge

No computador administrativo, atualize a cópia local da `main` e envie somente o
Compose, scripts e SQL versionados, sem `.git`, `.env`, secrets ou dumps:

```powershell
git switch main
git pull --ff-only origin main
ssh -i $env:USERPROFILE\.ssh\ff_actions_ed25519 ffdeploy@164.163.11.240 `
  "install -d -m 700 /tmp/frequenciafacil-release"
scp -i $env:USERPROFILE\.ssh\ff_actions_ed25519 -r `
  compose.prod.yaml database scripts `
  ffdeploy@164.163.11.240:/tmp/frequenciafacil-release
```

Em sessão administrativa autorizada na VPS, mantenha o secret com os metadados
acima, preserve as redes existentes e reinstale os arquivos. O instalador apenas
valida as redes e instala a configuração; ele não sobe, remove ou recria containers:

```bash
sudo bash /tmp/frequenciafacil-release/scripts/install-deploy-runner.sh wendell-ramos
sudo visudo -cf /etc/sudoers.d/frequenciafacil-deploy
sudo -l -U ffdeploy
```

Depois, um deploy autorizado continua atualizando exclusivamente `app` com
`up -d --no-build --no-deps app`; não use `--remove-orphans`. Verifique que o ID
do MySQL não mudou e que o app está ligado às duas redes:

```bash
mysql_id_before="$(sudo docker inspect -f '{{.Id}}' frequenciafacil-db-1)"
# execute o workflow manual somente após autorização e CI aprovado
test "$mysql_id_before" = "$(sudo docker inspect -f '{{.Id}}' frequenciafacil-db-1)"
sudo docker inspect frequenciafacil-app-1 \
  --format '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}'
curl -fsS http://127.0.0.1:8080/health
```

Valide o menor privilégio em nova sessão `ffdeploy`:

```bash
id
sudo -n -l
docker ps                         # deve continuar negado
test ! -w /usr/local/sbin/frequenciafacil-deploy
sudo -n /usr/local/sbin/frequenciafacil-deploy invalida  # deve recusar
```

## 6. Inspeção, backup e banco existente

Primeiro apenas inspecione:

```bash
sudo /usr/local/sbin/frequenciafacil-database
sudo docker ps --filter label=com.docker.compose.project=frequenciafacil
sudo docker volume inspect frequenciafacil_mysql_data
sudo docker network inspect frequenciafacil_backend
```

Crie e valide o backup. O comando não imprime senhas:

```bash
sudo /usr/local/sbin/frequenciafacil-backup
sudo ls -l /opt/frequenciafacil/backups
sudo sha256sum --check /opt/frequenciafacil/backups/frequenciafacil-AAAA*.sql.gz.sha256
```

O backup é válido tanto com zero quanto com várias tabelas. Ele só grava o artefato e
o manifesto depois de confirmar que o schema `frequenciafacil` existe na restauração
e que sua lista ordenada de tabelas/tipos é idêntica à origem.

Como a inspeção confirmou **zero tabelas**, inicialize as três tabelas versionadas com
o manifesto exato produzido acima:

```bash
sudo /usr/local/sbin/frequenciafacil-database --initialize-empty \
  /opt/frequenciafacil/backups/frequenciafacil-AAAAMMDDTHHMMSSZ.sql.gz.sha256
```

Esse modo confere novamente que a contagem é exatamente zero antes de executar
`database/schema.sql` e exige como resultado somente `alunos`, `presencas` e
`professores`. Se qualquer tabela aparecer entre inspeção e execução, o comando recusa
a inicialização. Não use `schema.sql` em banco parcial.

Para um banco não vazio que possua `alunos`/`presencas`, mas ainda não `professores`,
revise o estado e use apenas a migração incremental:

```bash
sudo /usr/local/sbin/frequenciafacil-database --apply-professores \
  /opt/frequenciafacil/backups/frequenciafacil-AAAAMMDDTHHMMSSZ.sql.gz.sha256
```

O CD nunca executa SQL. Não usar `down`, `down -v`, `volume prune`, não publicar 3306
e não substituir o Compose privado da VPS pelo Compose local.

## 7. Primeira conta de professor

Crie-a somente depois da migração e do primeiro app saudável, em sessão administrativa.
A senha é digitada sem eco, transformada em JSON na memória e enviada por stdin:

```bash
python3 -c 'import getpass,json,sys; t=open("/dev/tty"); print("Nome: ",end="",file=sys.stderr); n=t.readline().rstrip("\n"); print("E-mail: ",end="",file=sys.stderr); e=t.readline().rstrip("\n"); s=getpass.getpass("Senha (12 a 72 bytes): "); print(json.dumps({"nome":n,"email":e,"senha":s}))' |
  sudo docker compose --project-name frequenciafacil \
    --file /opt/frequenciafacil/compose.app.yaml exec -T app php bin/criar-professor.php
```

Não usar conta padrão e não armazenar essa senha no GitHub, `.env`, terminal, ticket ou log.

## 8. GitHub Environment, secrets e variables

Crie o environment `production`. Nele, cadastre:

- secrets `VPS_HOST`, `VPS_USER`, `VPS_SSH_PRIVATE_KEY`,
  `VPS_SSH_KNOWN_HOSTS`, `GHCR_USERNAME`, `GHCR_READ_TOKEN`;
- variables `DEPLOY_PATH=/opt/frequenciafacil`,
  `HEALTHCHECK_URL=http://127.0.0.1:8080/health`, `VPS_SSH_PORT=22`.

`VPS_HOST=164.163.11.240` e `VPS_USER=ffdeploy`. Cadastre a chave privada diretamente
na interface do GitHub; nunca a envie em chat ou commit. `GHCR_READ_TOKEN` deve ter
somente leitura de packages. Ative revisores obrigatórios no environment, se o plano
permitir.

`ENABLE_PRODUCTION_CD` deve ser **variável do repositório**, porque ela decide se o job
será criado antes de acessar o environment. Mantenha-a como `false`. Não a coloque em
secrets nem a ative para o primeiro deploy.

Gere `VPS_SSH_KNOWN_HOSTS` fora do workflow e compare a chave obtida com a fingerprint
confirmada no próprio servidor:

```bash
ssh-keyscan -p 22 -t ed25519 164.163.11.240 > vps_known_hosts
ssh-keygen -lf vps_known_hosts
# deve mostrar SHA256:Yiq2+MhvHalcJv1no5MbvJmyB67IgtvPluA/Raiti9A
```

Cole o conteúdo de `vps_known_hosts` no secret e apague o arquivo local após o cadastro.

## 9. HTTPS, Nginx e Netdata

Não foi encontrado domínio na configuração. Sem domínio não há certificado público
normal para esse IP. Antes do primeiro deploy, coloque o Nginx em modo de manutenção;
isso mantém o app acessível apenas por túnel SSH:

```bash
sudo cp -a /etc/nginx/sites-available/frequenciafacil \
  /etc/nginx/sites-available/frequenciafacil.before-first-deploy
sudo install -o root -g root -m 0644 \
  /tmp/frequenciafacil-release/ops/nginx/frequenciafacil-maintenance.conf \
  /etc/nginx/sites-available/frequenciafacil
sudo nginx -t && sudo systemctl reload nginx
curl -i http://127.0.0.1/             # esperado: 503
```

Valide o app pelo computador do administrador:

```powershell
ssh -i $env:USERPROFILE\.ssh\ff_actions_ed25519 -L 18080:127.0.0.1:8080 ffdeploy@164.163.11.240
```

Então abra `http://127.0.0.1:18080/health`. O login em produção usa cookie Secure;
teste funcional do navegador exige o HTTPS final, não desative esse atributo.

Quando houver domínio apontando para a VPS, substitua `app.exemplo.com` no modelo HTTPS,
emita certificado confiável (por exemplo, Certbot do repositório oficial Ubuntu), execute
`nginx -t` e só então recarregue. O modelo envia `X-Forwarded-Proto=https`, aplica headers
de segurança e limita `/api/auth/login` a 5 requisições/minuto por IP com burst 5.

A porta 19999 do Netdata está pública. Antes do deploy, identifique o processo/container:

```bash
sudo ss -lntp | grep ':19999'
sudo docker ps --format '{{.Names}} {{.Image}} {{.Ports}}' | grep -i netdata || true
sudo ufw status verbose
```

Se for container, recrie somente o serviço Netdata com bind `127.0.0.1:19999:19999`.
Se for nativo, configure `[web] bind to = 127.0.0.1` em `/etc/netdata/netdata.conf`.
Valide com `ss` e acesse por túnel SSH. Não use apenas UFW para uma porta publicada por
Docker sem também conferir as regras `DOCKER-USER`.

## 10. Primeiro deploy controlado

Pré-requisitos obrigatórios:

1. PR revisado e incorporado à `main`; CI de `push/main` aprovado.
2. Runner restrito instalado e testado.
3. MySQL/volume/rede inspecionados; backup restaurado com sucesso em banco descartável.
4. Banco vazio inicializado com as três tabelas e conferido; banco parcial usa migração revisada.
5. Nginx em manutenção; Netdata não público; 3306 ausente do host.
6. Secrets/variables preenchidos; `ENABLE_PRODUCTION_CD=false`.
7. Autorização explícita do responsável.

Após a autorização, execute manualmente o workflow `CD`, informe o SHA completo atual
da `main` e `DEPLOY-CONTROLADO`. Esse caminho publica e implanta uma única versão sem
ativar o CD recorrente. Confira Actions e, na VPS:

```bash
sudo docker ps --filter label=com.docker.compose.project=frequenciafacil
curl -fsS http://127.0.0.1:8080/health
sudo docker inspect frequenciafacil-db-1 --format '{{.Id}} {{json .Mounts}}'
sudo tail -n 100 /var/log/nginx/frequenciafacil.error.log
sudo docker logs --tail 100 "$(sudo docker ps -q --filter label=com.docker.compose.service=app)"
```

Crie a primeira conta e valide login, logout, cookie `Secure; HttpOnly; SameSite=Lax`,
CSRF, CRUD de dados de teste, histórico e ausência de segredos nos logs. Não use dados
reais nessa etapa.

## 11. Rollback e ativação automática

Pull inválido não altera o app. Falha de start ou health após a troca chama a imagem
anterior. O script registra separadamente se a restauração e seu healthcheck passaram.
O primeiro deploy não tem imagem anterior; portanto rollback não existe até haver ao
menos uma versão saudável anterior. Não declarar o rollback validado antes de um ensaio
controlado com duas imagens aprovadas, Nginx em manutenção e confirmação de que o ID do
MySQL/volume permaneceu intacto.

Depois do primeiro deploy, HTTPS e ensaio de rollback aprovados, a autorização final
consiste em alterar a variável de repositório:

```text
ENABLE_PRODUCTION_CD=true
```

A partir daí: push/merge em `main` → CI aprovado → build do mesmo SHA → GHCR → digest
imutável → SSH → somente `app` → healthcheck → sucesso ou tentativa de rollback. PRs
não publicam, execuções concorrentes ficam serializadas e commits antigos são recusados.

## 12. Resultados e pendências

| Etapa | Estado |
|---|---|
| Auditoria GitHub/main/CI/CD | Concluída e testada |
| Auditoria SSH, fingerprint, swap, arquivos e portas | Concluída e testada |
| Compose app-only com Docker Secret | Concluída e testada localmente |
| CI, autenticação, frontend e MySQL descartável | Concluída e testada localmente |
| Backup vazio/populado e inicialização protegida | Concluída e testada em banco descartável; VPS pendente |
| Wrapper de deploy/rollback | Implementado, mas ainda não testado na VPS |
| Instalação sudoers e acesso Docker controlado | Dependente de configuração administrativa |
| Inspeção/backup/migração do MySQL real | Dependente de configuração administrativa |
| GHCR/secrets/variables/environment | Dependente do proprietário do repositório |
| Domínio, certificado HTTPS e rate limit | Bloqueada por domínio não informado |
| Fechamento da porta pública do Netdata | Dependente de configuração administrativa |
| Primeiro deploy e ensaio de rollback | Bloqueado até backup, HTTPS/isolamento e autorização |
| `ENABLE_PRODUCTION_CD=true` | Bloqueado até autorização final |
