# FrequênciaFácil

Sistema web para o professor cadastrar alunos, registrar presença por data e consultar o histórico.
Atividade de DevOps — Processo de Desenvolvimento de Software (Prof. Alisson Kuhn), Grupo 2.

**Stack:** PHP 8.3 · MySQL 8.4 · HTML/CSS/JS · Docker Compose · GitHub Actions · VPS Locaweb

## Rodar localmente

Precisa só de Docker.

```bash
cp .env.example .env
docker compose up -d --build
```

- Telas: http://localhost:8080
- API: http://localhost:8080/api/health
- Logs: `docker compose logs -f app`
- Zerar o banco: `docker compose down -v`

### Testes

```bash
composer install
vendor/bin/phpunit
```

## Como contribuir

1. Pegue um card no Trello e mova para *Em Desenvolvimento*
2. `git checkout -b feature/nome-curto`
3. Commit, push e abra um Pull Request para a `main`
4. CI verde + 1 aprovação → merge → deploy automático

## Documentação

- [Arquitetura e pastas](docs/arquitetura.md)
- [Contrato da API](docs/api.md)
- [Pipeline CI/CD](docs/pipeline.md)
- [Monitoramento](docs/monitoramento.md)
- [Scrum](docs/scrum.md)
