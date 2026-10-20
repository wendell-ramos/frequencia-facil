# Contrato da API

Este arquivo é o acordo entre backend (Wendell) e frontend (Diogo).
Os dois podem trabalhar em paralelo seguindo o que está aqui.
Se mudar algo, mude **aqui primeiro** e avise no grupo.

- Base: `/api`
- Formato: JSON (`Content-Type: application/json`), campos em camelCase
- Datas: `AAAA-MM-DD`
- Erro sempre neste formato:

```json
{ "erro": "Mensagem clara para mostrar na tela", "detalhes": { "campo": "mensagem do campo" } }
```

| Status | Quando |
|---|---|
| 200 | OK |
| 201 | Criado |
| 204 | Excluído (sem corpo) |
| 404 | Não encontrado |
| 409 | Duplicado (mesmo nome + telefone) |
| 422 | Dados inválidos (ver `detalhes`) |
| 500 | Erro interno (vai para o log) |
| 501 | Ainda não implementado |

---

## Monitoramento

### `GET /api/health` ✅ implementado
```json
{ "status": "ok", "banco": "ok", "horario": "2026-10-20T12:00:00-03:00", "versao": "a1b2c3d" }
```
503 com `"banco": "indisponivel"` se o MySQL não responder.

---

## Alunos — US01 (itens 2 e 3)

### `GET /api/alunos?busca=ana`
```json
[
  { "id": 1, "nome": "Ana Souza", "telefone": "(18) 99711-0001", "dataNascimento": "2004-03-12", "criadoEm": "2026-10-20 12:00:00" }
]
```

### `GET /api/alunos/{id}`
Um objeto aluno. 404 se não existir.

### `POST /api/alunos`
Entrada:
```json
{ "nome": "Ana Souza", "telefone": "(18) 99711-0001", "dataNascimento": "2004-03-12" }
```
- `nome` e `telefone` obrigatórios; `dataNascimento` opcional
- 201 com o aluno criado
- 422 `{ "erro": "Dados inválidos", "detalhes": { "nome": "Informe o nome" } }`
- 409 `{ "erro": "Já existe um aluno com este nome e telefone" }`

### `PUT /api/alunos/{id}`
Mesma entrada e mesmas regras do POST. 200 com o aluno atualizado.

### `DELETE /api/alunos/{id}`
204. Apaga também as presenças do aluno.

---

## Presença — US02 (item 5)

### `GET /api/presencas?data=2026-10-20`
Todos os alunos com o status naquela data (`null` = ainda não registrado). Sem `data` usa hoje.
```json
{
  "data": "2026-10-20",
  "alunos": [
    { "alunoId": 1, "nome": "Ana Souza",  "presente": true },
    { "alunoId": 2, "nome": "Bruno Lima", "presente": null }
  ]
}
```

### `POST /api/presencas`
Grava a chamada inteira de uma vez. Se já existir registro do aluno na data, atualiza.
```json
{
  "data": "2026-10-20",
  "registros": [
    { "alunoId": 1, "presente": true },
    { "alunoId": 2, "presente": false }
  ]
}
```
200 `{ "mensagem": "Presença salva", "total": 2 }`

---

## Histórico — US03 (item 6)

### `GET /api/alunos/{id}/presencas?inicio=2026-10-01&fim=2026-10-31`
`inicio` e `fim` opcionais.
```json
{
  "aluno": { "id": 1, "nome": "Ana Souza" },
  "totais": { "presencas": 7, "faltas": 1, "total": 8 },
  "registros": [
    { "dataAula": "2026-10-20", "presente": true },
    { "dataAula": "2026-10-13", "presente": false }
  ]
}
```
Sem registros: `registros: []` e totais zerados — o front mostra "Nenhum registro no período".
