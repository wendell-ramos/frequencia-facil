# Arquitetura e organização das pastas

```
frequencia-facil/
├── .github/
│   ├── workflows/
│   │   ├── ci.yml              # CI: lint + testes a cada PR/push na main
│   │   └── cd.yml              # CD: imagem Docker -> GHCR -> deploy na VPS -> health check
│   └── pull_request_template.md
├── public/                     # Raiz do servidor web (único lugar acessível pelo navegador)
│   ├── index.php               # Entrada da API: toda URL /api/... cai aqui
│   ├── .htaccess               # Regra: arquivo existe? serve. Senão, vai para index.php
│   ├── index.html              # Telas (Diogo)
│   ├── alunos.html
│   ├── presenca.html
│   ├── historico.html
│   └── assets/
│       ├── css/style.css
│       └── js/
│           ├── api.js          # Todas as chamadas fetch() para a API
│           ├── alunos.js
│           ├── presenca.js
│           └── historico.js
├── src/                        # Backend PHP (namespace App\)
│   ├── routes.php              # Lista de rotas -> controller@método
│   ├── Core/                   # Base pronta (não precisa mexer)
│   │   ├── Router.php  Request.php  Response.php
│   │   ├── Database.php        # Conexão PDO com o MySQL
│   │   ├── Config.php
│   │   └── Logger.php          # Log de erros em JSON (monitoramento)
│   ├── Controllers/            # Recebem a requisição e devolvem a resposta (Wendell)
│   ├── Services/               # Regras de negócio e validação (Wendell)
│   ├── Repositories/           # SQL (Wendell)
│   └── Models/                 # Aluno, Presenca
├── config/config.php           # Lê as variáveis de ambiente
├── database/                   # Scripts SQL, rodados na criação do banco
│   ├── 001_schema.sql
│   └── 002_seed.sql
├── tests/
│   ├── Unit/                   # Testes sem banco (Thiago + quem fez o código)
│   └── Integration/            # Testes com MySQL (o CI sobe um)
├── docs/                       # Documentação entregue ao professor
├── logs/                       # app.log (fora do Git)
├── Dockerfile                  # Imagem PHP 8.3 + Apache
├── docker-compose.yml          # Infra como código: app + MySQL
├── composer.json  phpunit.xml
├── .env.example                # Modelo de variáveis (o .env real não vai pro Git)
└── README.md
```

## Caminho de uma requisição

```
Navegador ── GET /alunos.html ──────────────► Apache serve o arquivo estático
Navegador ── fetch('/api/alunos') ──► public/index.php
                                       └► Router (src/routes.php)
                                           └► AlunoController::index
                                               └► AlunoService (regras)
                                                   └► AlunoRepository (SQL)
                                                       └► MySQL
```

Tudo roda num único container `app` (frontend + API) mais o container `db`.
Isso economiza memória na VPS e evita problemas de CORS.

## Quem mexe onde

| Pasta | Responsável |
|---|---|
| `public/*.html`, `public/assets/` | Diogo (frontend) |
| `src/Controllers`, `Services`, `Repositories`, `Models`, `database/` | Wendell (backend/banco) |
| `Dockerfile`, `docker-compose.yml`, `.github/`, `tests/` | Thiago (DevOps/QA) |
| `docs/`, README | Vinicius (Scrum Master) + todos |
| Backlog, critérios de aceite | Guilherme (PO) |

## Por que separar Controller / Service / Repository?

- **Controller** não sabe SQL; só traduz HTTP ↔ PHP.
- **Service** guarda as regras dos critérios de aceite; dá pra testar sem banco.
- **Repository** é o único lugar com SQL; se o banco mudar, só ele muda.
