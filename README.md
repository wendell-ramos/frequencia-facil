# FrequênciaFácil

Sistema web de cadastro de alunos, registro de presença e consulta de histórico.

## Autenticação do backend

Páginas e APIs exigem sessão de professor. Antes de usar, aplique a migração de
professores e crie uma conta pelo comando administrativo. Não há conta padrão nem
cadastro público. A tela `/login` faz a entrada; o navegador sem sessão é levado até ela.
Veja [docs/autenticacao.md](docs/autenticacao.md) para configuração, primeiro acesso,
Insomnia e HTTPS em produção. O health check permanece público.

## Tecnologias e estrutura

PHP 8.3 puro, Apache com mod_rewrite e .htaccess, MySQL 8.4, PDO, Composer
com autoload PSR-4 e PHPUnit 11 para testes.

```text
app/Config/         Carregamento do .env e conexão PDO
app/Controllers/    Telas, APIs de alunos/presenças/histórico e verificação do banco
app/Http/           Respostas JSON e erros esperados da API
app/Services/       Validação, persistência e consulta do histórico com PDO
app/Views/          Páginas PHP (layout/ tem o topo e o rodapé comuns)
assets/             CSS e JavaScript das telas, servidos direto pelo Apache
database/schema.sql Tabelas alunos e presencas
database/migrations/ Futuras alterações incrementais
docker/             Dockerfile e configuração do Apache
tests/              Testes de configuração, telas, validação e endpoints
index.php           Entrada e rotas da aplicação
compose.yaml        Ambiente Docker local
```

A API de alunos implementa cadastro, listagem, consulta, edição e exclusão.
A API de presença consulta a chamada por data e salva/atualiza marcações em lote.
A API de histórico consulta um aluno por período e retorna presenças, faltas e totais.
As telas usam a API real, com login, sessão e token CSRF integrados no frontend.

## Configuração

Copie `.env.example` para `.env` e preencha os valores locais. Nunca versione
o arquivo real. Variáveis definidas pelo servidor têm prioridade sobre o arquivo.
O carregador aceita `NOME=valor`, comentários em linhas iniciadas por `#` e
valores entre aspas. Não faz interpolação `${VAR}` nem comentários no fim da linha.

| Variável | Apache local / XAMPP | Docker local |
| --- | --- | --- |
| `APP_BASE_PATH` | `/frequencia-facil` | vazio, definido pelo Compose |
| `APP_PORT` | não altera o Apache local | `8080`, alterável |
| `DB_HOST` | `127.0.0.1` | `db`, definido pelo Compose |
| `DB_PORT` | porta do MySQL local | `3306`, definido pelo Compose |
| `MYSQL_DATABASE` | `frequenciafacil` | `frequenciafacil` |
| `MYSQL_USER` | usuário local autorizado | `frequencia_app` |
| `MYSQL_PASSWORD` | senha local | definir antes de iniciar |
| `MYSQL_ROOT_PASSWORD` | não usado pela aplicação | definir antes de iniciar |

`APP_ENV=local` identifica o ambiente de desenvolvimento. Use senhas próprias
e diferentes para os dois usuários. Aspas simples no `.env` preservam `$` e `#`
também no Compose. Não use root como usuário da aplicação no Docker.

## Docker local

Com Docker Desktop iniciado, copie `.env.example` para `.env`, preencha as duas
senhas e execute na raiz:

```powershell
docker compose up -d --build
docker compose ps
docker compose logs app db
```

Acesse `http://localhost:8080/` e `http://localhost:8080/health`. Ajuste o endereço
se alterar `APP_PORT`. Não é necessário instalar PHP ou Composer no Windows para
executar esse ambiente: a imagem instala as dependências.
Após mudar o código, execute novamente `docker compose up -d --build`.
O banco não publica uma porta no computador hospedeiro.

O schema é aplicado automaticamente apenas na primeira inicialização de um volume
vazio. `docker compose down` preserva `mysql_data`. Não use `docker compose down -v`,
pois apaga o volume com os dados. Alterações posteriores exigem scripts incrementais;
editar `schema.sql` não atualiza automaticamente um banco existente.

## Apache local / XAMPP

Requisitos: PHP 8.3 com pdo_mysql, Composer, Apache com mod_rewrite e
AllowOverride All, e servidor MySQL 8.4 disponível. O PHP 8.2 do XAMPP não atende
à versão do projeto; não é necessário atualizar o XAMPP se usar Docker.
O banco incluído no XAMPP pode ser MariaDB: confira o servidor utilizado.
Workbench é um cliente e não substitui o servidor MySQL.

1. Configure o `.env` com host, porta e usuário do banco local.
2. Confira `php -v` e execute `composer install` com PHP 8.3.
3. No Workbench, crie o schema `frequenciafacil`, charset `utf8mb4`, collation
   `utf8mb4_unicode_ci`, e um usuário autorizado para esse banco.
4. Selecione o schema como padrão e execute `database/schema.sql`.
5. Com o Apache iniciado e o projeto em `htdocs/frequencia-facil`, abra
   `http://localhost/frequencia-facil/`.

O PHP não cria bancos, usuários ou tabelas ao receber requisições web.

## Rotas e testes

| Método | Caminho relativo | Resultado |
| --- | --- | --- |
| GET | `/login` | Entrar (pública). Navegador sem sessão em outra tela é redirecionado para cá |
| GET | `/` | Início: data de hoje e situação da chamada |
| GET | `/alunos` | Cadastro, edição e exclusão de alunos |
| GET | `/chamada` | Registro de presença por data |
| GET | `/historico` | Histórico e percentual de frequência por aluno |
| GET | `/health` | HTTP 200 com banco acessível; 503 se a conexão falhar |

Rotas desconhecidas retornam 404 e métodos não permitidos retornam 405.
O health check executa `SELECT 1`; não valida o schema. Erros detalhados ficam
nos logs, sem credenciais na resposta HTTP. O .htaccess bloqueia acesso web a
configurações, dependências e scripts SQL.

Com PHP 8.3 e dependências de desenvolvimento:

```powershell
composer install
composer test
```

Os testes de configuração, telas e validação rodam sem banco. Os testes HTTP
de alunos ficam ignorados nessa execução e são executados pelo CI contra
Apache/PHP e MySQL 8.4 descartáveis. Para repetir a verificação completa, com
Docker aberto, execute na raiz pelo Git Bash (ou em Linux):

```bash
docker build --target production -f docker/Dockerfile -t frequenciafacil-backend:local .
docker build --target test -f docker/Dockerfile -t frequenciafacil-backend-test:local .
bash scripts/test-infrastructure.sh frequenciafacil-backend:local frequenciafacil-backend-test:local
```

O script usa a porta local 18080, rede temporária e MySQL em `tmpfs`, sem montar
o volume do projeto. Cria o schema, verifica as páginas e executa os testes
dos endpoints de alunos e presenças, incluindo duplicidade, bloqueio de exclusão
com histórico, atualização sem duplicar, rollback de chamadas inválidas e filtros
inclusivos do histórico com totais calculados somente no período selecionado.
Remove seus containers e sua rede ao terminar. Os testes de escrita exigem
`FF_TEST_BASE_URL` e `MYSQL_DATABASE=frequenciafacil_test`; não use credenciais
do banco normal. O contrato e as validações estão em `docs/api.md`.

Para testar manualmente no Insomnia, use `http://localhost:8080/api/alunos`
após `docker compose up -d --build`, migração e criação de professor. Faça primeiro
o fluxo de sessão/login de `docs/autenticacao.md`, preserve o cookie e envie
`X-CSRF-Token` nas gravações. POST/PUT usam JSON, por exemplo:

```json
{ "nome": "Aluno Exemplo", "telefone": "(18) 99711-0001", "dataNascimento": "2000-02-29" }
```

GET na coleção lista os alunos; GET/PUT/DELETE em `/api/alunos/{id}` consultam,
editam ou excluem. Esses pedidos manuais usam o banco local persistente.

Para presença, `GET /api/presencas?data=2000-01-01` consulta a chamada
(`GET /api/presencas` usa hoje). `POST /api/presencas` recebe um JSON como:

```json
{ "data": "2000-01-01", "registros": [{ "alunoId": 1, "presente": true }] }
```

Use o ID de um aluno cadastrado. Um novo envio para o mesmo aluno/data atualiza
a marcação. O lote é salvo por inteiro ou desfeito se houver erro; não são
aceitas datas futuras, IDs repetidos no envio ou valores diferentes de
`true`/`false`. Essas requisições também exigem sessão e token CSRF.

Para histórico, use `GET /api/alunos/{id}/presencas`. Os filtros `inicio` e `fim`
são opcionais, por exemplo:
`GET /api/alunos/1/presencas?inicio=2000-01-01&fim=2000-01-31`.
A resposta inclui o aluno, os totais de presenças/faltas e os registros em ordem
decrescente de data. Períodos sem marcações retornam lista vazia e totais zerados;
datas sem lançamento não são convertidas em faltas. Consulte `docs/api.md`.

## Banco e integração com DevOps

- `alunos`: nome, telefone e nascimento; combinação nome/telefone única.
- `presencas`: aluno, data e presente/ausente; combinação aluno/data única.
- A chave estrangeira impede excluir alunos com histórico; a API responde 409
  com uma mensagem clara, conforme a decisão do PO registrada em `docs/api.md`.
- Versione scripts SQL e somente dados fictícios, nunca dumps de produção.

O Compose deste repositório é uma base local, com a aplicação publicada apenas
em `127.0.0.1`. O Thiago deve integrá-lo ao Nginx, redes, volumes e configuração
da VPS. Não substitua o Compose ou o `.env` da VPS pelos exemplos locais.
Na VPS, use `DB_HOST=db`, `DB_PORT=3306`, `APP_BASE_PATH` vazio e as credenciais
privadas do servidor. O código não depende de caminhos do XAMPP.

Trabalhe em branches `feature/...`, abra PR para `main` e aguarde a revisão
combinada com Canducci. CI/CD e configuração da VPS ficam com DevOps.

Uma entrega que depende de outra ainda em revisão pode usar a branch anterior
como base do PR. Depois do merge da dependência, ajuste a base para `main` e
confira novamente o diff e o CI. O CI também aceita PRs com base em
`feature/backend-*` para esse fluxo. Alunos e presenças já foram incorporados à
`main`; a branch `feature/backend-historico` parte dela e deve abrir PR para `main`.

## CI/CD e imagem de producao

O workflow de CI valida Composer, executa o PHPUnit existente, verifica a
sintaxe PHP, valida os dois arquivos Compose, constroi a imagem e testa o
endpoint real `/health` contra um MySQL 8.4 temporario. Consulte
[`docs/infraestrutura.md`](docs/infraestrutura.md) para os limites desses testes.

O `compose.yaml` continua sendo o ambiente local do backend. Em producao,
`compose.prod.yaml` deve ser aplicado como segundo arquivo: ele exige uma imagem
GHCR com tag do commit e transforma `mysql_data` em volume externo. Isso impede
que o deploy da aplicacao crie um volume novo por engano. O script de deploy
atualiza somente `app` com `--no-build --no-deps` e nunca executa `down`.

O CD esta desativado por padrao. Mesmo depois de integrado na `main`, ele so
prossegue quando o CI dessa `main` termina com sucesso e a variavel de repositorio
`ENABLE_PRODUCTION_CD` vale `true`. Nao habilite antes de cumprir o checklist da
documentacao e conferir o volume atual da VPS.

Referências: [PHP Docker](https://hub.docker.com/_/php),
[MySQL Docker](https://hub.docker.com/_/mysql),
[Composer](https://getcomposer.org/doc/) e [Apache](https://httpd.apache.org/docs/2.4/).
