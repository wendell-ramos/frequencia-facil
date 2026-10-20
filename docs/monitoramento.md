# Monitoramento

| O quê | Como | Onde ver |
|---|---|---|
| Aplicação está no ar? | `GET /api/health` (testa também o banco) | navegador / último passo do CD |
| Container saudável? | `HEALTHCHECK` no Dockerfile | `docker compose ps` (coluna STATUS) |
| Erros da aplicação | `App\Core\Logger` grava JSON em stderr e `logs/app.log` | `docker compose logs -f app` |
| Exceções não tratadas | capturadas em `public/index.php`, viram 500 + log ERROR | idem |
| Falhas de deploy | health check do CD | aba Actions do GitHub |

Exemplo de linha de log:

```json
{"time":"2026-10-20T12:00:00-03:00","level":"ERROR","message":"Erro não tratado","context":{"method":"POST","path":"/api/alunos","exception":"PDOException","message":"..."}}
```

## Extras gratuitos (opcional)

- **UptimeRobot**: chama `/api/health` a cada 5 min e manda e-mail se cair.
- **Grafana + Loki** ou **Dozzle** (`amir20/dozzle`) para ver os logs no navegador.
