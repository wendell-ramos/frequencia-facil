# Contrato da API

Este arquivo é o acordo entre backend (Wendell) e frontend (Diogo) — **proposta**, ajuste à vontade.
Os dois podem trabalhar em paralelo seguindo o que está aqui.
Se mudar algo, mude **aqui primeiro** e avise no grupo.

- Base: `<APP_BASE_PATH>/api` — no Docker/VPS é `/api`; no XAMPP, `/frequencia-facil/api`
- As telas (`app/Views`) chamam a API pelo JavaScript em `assets/js/api.js`, usando a base que o PHP grava em `<body data-base>`
- Formato: JSON (`Content-Type: application/json`), campos em camelCase
- Datas: `AAAA-MM-DD`
- Erro sempre neste formato (mesmo padrão `mensagem` que o index.php já usa):

```json
{ "mensagem": "Mensagem clara para mostrar na tela", "erros": { "campo": "mensagem do campo" } }
```

| Status | Quando |
|---|---|
| 200 | OK |
| 201 | Criado |
| 204 | Excluído (sem corpo) |
| 400 | JSON malformado ou corpo que não é um objeto JSON |
| 404 | Não encontrado |
| 405 | Método não permitido (métodos aceitos no cabeçalho `Allow`) |
| 409 | Conflito: aluno duplicado, ou exclusão de aluno com histórico |
| 415 | POST/PUT sem `Content-Type: application/json` |
| 422 | Dados inválidos (ver `erros`) |
| 500 | Erro interno (vai para o log) |
| 501 | Ainda não implementado |

---

## Monitoramento

### `GET /health` ✅ já existe (HealthController)
```json
{ "status": "ok", "banco": "conectado" }
```
503 com `"status": "indisponivel"` se o MySQL não responder. Fica fora de `/api` porque é usado pelo Docker/CD, não pelas telas.

---

## Alunos — US01 (itens 2 e 3)

Endpoints de alunos implementados. Presença e histórico continuam planejados.
O frontend permanece em demonstração até a integração feita pelo Diogo.
`USAR_MOCK` controla todas as telas: a troca global depende também das APIs de
presença/histórico; para integrar somente alunos antes disso, o Diogo deve
separar essa escolha por recurso.

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
- `nome`: texto com 3 a 150 caracteres; espaços nas extremidades são removidos e espaços repetidos são reduzidos
- `telefone`: texto com DDD e 10 ou 11 dígitos, aceitando espaços, parênteses e hífen; salvo com máscara padronizada
- A duplicidade usa a comparação de nomes do MySQL (`utf8mb4_unicode_ci`, sem distinguir caixa/acentos) e os dígitos do telefone, inclusive para registros antigos sem máscara
- `dataNascimento`: `null`, vazio ou data real no formato `AAAA-MM-DD`, de 1000-01-01 até hoje no fuso `America/Sao_Paulo`
- POST/PUT exigem um objeto JSON e `Content-Type: application/json`; PUT substitui os campos do aluno
- 201 com o aluno criado
- 422 `{ "mensagem": "Dados inválidos", "erros": { "nome": "Informe o nome" } }`
- 409 `{ "mensagem": "Já existe um aluno com este nome e telefone" }`

O cadastro retorna também `Location: <APP_BASE_PATH>/api/alunos/{id}`.
A listagem ordena por nome e ID. `busca` é opcional, tem até 150 caracteres e procura texto literal dentro do nome.
ID inexistente ou fora do intervalo de inteiro positivo do banco retorna 404.

### `PUT /api/alunos/{id}`
Mesma entrada e mesmas regras do POST. 200 com o aluno atualizado.

### `DELETE /api/alunos/{id}`
204 quando excluído.

O `schema.sql` usa `ON DELETE RESTRICT`: aluno com presenças registradas **não** pode ser excluído.
Nesse caso: 409 `{ "mensagem": "Não é possível excluir: o aluno já tem presenças registradas." }`.
✅ Decisão do PO (20/10): aluno com presenças registradas não pode ser excluído.

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
