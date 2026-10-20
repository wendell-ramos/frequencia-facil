# Pipeline DevOps

```
feature/xxx ──► Pull Request ──► CI (lint + testes + build Docker)
                    │                 │
                    │            falhou? ──► volta pro autor
                    ▼
            revisão (1 aprovação) ──► merge na main ──► CI de novo na main
                                                          │ passou
                                                          ▼
                                   CD: build imagem ─► push GHCR ─► SSH na VPS
                                       ─► docker compose pull/up ─► health check
```

## 1. Git e GitHub

- `main` protegida: Settings → Branches → Add rule
  - Require a pull request before merging (1 aprovação)
  - Require status checks to pass: `Lint + testes` e `Build da imagem Docker`
- Branches: `feature/cadastro-aluno`, `fix/validacao-telefone`, `docs/pipeline`
- Commits: `feat: cadastra aluno`, `fix: ...`, `test: ...`, `docs: ...`
- Trello: Power-Up do GitHub para ligar o PR ao card

## 2. CI — `.github/workflows/ci.yml`

Dispara em todo PR para a `main` e todo push na `main`.

1. Checkout
2. Instala PHP 8.3 com `pdo_mysql`
3. `composer install`
4. `composer lint` (`php -l` em todos os arquivos)
5. Sobe um MySQL temporário e cria as tabelas
6. `vendor/bin/phpunit` (unitários + integração)
7. Em paralelo: `docker build` para garantir que a imagem compila

## 3. CD — `.github/workflows/cd.yml`

Só roda se o CI da `main` passou.

1. Build da imagem e push para `ghcr.io/<org>/<repo>` (tags `latest` e o SHA do commit)
2. Copia `docker-compose.yml` e `database/` para `/opt/frequencia-facil` na VPS
3. SSH: `docker compose pull app && docker compose up -d`
4. Health check em `APP_URL/api/health` (até 2 minutos). Falhou = deploy vermelho no GitHub.

### Secrets (Settings → Secrets and variables → Actions)

| Secret | Exemplo |
|---|---|
| `VPS_HOST` | IP da VPS |
| `VPS_USER` | `deploy` |
| `VPS_SSH_KEY` | conteúdo da chave privada |
| `APP_URL` | `http://IP-DA-VPS` |

Nenhuma senha vai para o repositório.

## 4. Preparar a VPS (uma vez só — Thiago)

```bash
# Docker + Compose
curl -fsSL https://get.docker.com | sh

# Usuário só para deploy, sem senha, com acesso ao Docker
adduser --disabled-password --gecos "" deploy
usermod -aG docker deploy
mkdir -p /opt/frequencia-facil && chown deploy:deploy /opt/frequencia-facil

# Chave SSH do GitHub Actions
ssh-keygen -t ed25519 -f gh-deploy -N ""          # gera gh-deploy e gh-deploy.pub
mkdir -p /home/deploy/.ssh
cat gh-deploy.pub >> /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh && chmod 700 /home/deploy/.ssh
# conteúdo de gh-deploy vai no secret VPS_SSH_KEY; depois apague o arquivo

# .env de produção (fica só na VPS)
cat > /opt/frequencia-facil/.env <<'ENV'
APP_ENV=production
APP_DEBUG=false
APP_PORT=80
DB_DATABASE=frequencia
DB_USERNAME=frequencia
DB_PASSWORD=<senha forte>
DB_ROOT_PASSWORD=<outra senha forte>
ENV
chmod 600 /opt/frequencia-facil/.env
```

Depois que todos tiverem chave SSH: desligar login por senha e de root
(`PasswordAuthentication no` e `PermitRootLogin prohibit-password` em `/etc/ssh/sshd_config`).
