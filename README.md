# FrequênciaFácil

Sistema web de cadastro de alunos, registro de presença e consulta de histórico.

## Tecnologias e estrutura

PHP 8.3 puro, Apache com mod_rewrite e .htaccess, MySQL 8.4, PDO, Composer
com autoload PSR-4 e PHPUnit 11 para testes.

```text
app/Config/         Carregamento do .env e conexão PDO
app/Controllers/    Telas (Home e PaginaController) e verificação do banco
app/Services/       Regras de negócio dos próximos cards
app/Views/          Páginas PHP (layout/ tem o topo e o rodapé comuns)
assets/             CSS e JavaScript das telas, servidos direto pelo Apache
database/schema.sql Tabelas alunos e presencas
database/migrations/ Futuras alterações incrementais
docker/             Dockerfile e configuração do Apache
tests/              Testes de configuração
index.php           Entrada e rotas da aplicação
compose.yaml        Ambiente Docker local
```

A estrutura inicial ainda não implementa o CRUD nem as histórias de presença.

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

Os testes iniciais validam o carregamento das variáveis e a prioridade do ambiente,
sem exigir banco. Testes de CRUD serão incluídos junto com essas funcionalidades.

## Banco e integração com DevOps

- `alunos`: nome, telefone e nascimento; combinação nome/telefone única.
- `presencas`: aluno, data e presente/ausente; combinação aluno/data única.
- A chave estrangeira impede excluir alunos com histórico. Alinhar a política
  de exclusão com o PO antes de implementar essa operação.
- Versione scripts SQL e somente dados fictícios, nunca dumps de produção.

O Compose deste repositório é uma base local, com a aplicação publicada apenas
em `127.0.0.1`. O Thiago deve integrá-lo ao Nginx, redes, volumes e configuração
da VPS. Não substitua o Compose ou o `.env` da VPS pelos exemplos locais.
Na VPS, use `DB_HOST=db`, `DB_PORT=3306`, `APP_BASE_PATH` vazio e as credenciais
privadas do servidor. O código não depende de caminhos do XAMPP.

Trabalhe em branches `feature/...`, abra PR para `main` e aguarde a revisão
combinada com Canducci. CI/CD e configuração da VPS ficam com DevOps.

Referências: [PHP Docker](https://hub.docker.com/_/php),
[MySQL Docker](https://hub.docker.com/_/mysql),
[Composer](https://getcomposer.org/doc/) e [Apache](https://httpd.apache.org/docs/2.4/).
