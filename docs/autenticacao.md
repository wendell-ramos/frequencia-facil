# Autenticação do professor — contrato do backend

O acesso aos dados é compartilhado entre os professores ativos deste MVP. Não há
cadastro público, perfis administrativos, recuperação de senha ou separação por turma.
A tela `/login` e `assets/js/api.js` já estão integrados ao backend. Navegadores sem
sessão são redirecionados para o login; clientes de API continuam recebendo HTTP 401
em JSON. `/health`, `/login` e os arquivos estáticos permanecem públicos.

## Banco e primeira conta

Banco novo: executar `database/schema.sql`, que também cria `professores`.
Banco existente: fazer backup verificado e aplicar **somente**
`database/migrations/001_create_professores.sql`, no mesmo schema da aplicação.
A migração é aditiva; não modifica nem remove alunos ou presenças. O deploy não a executa
automaticamente. Ela precisa estar aplicada antes da publicação desta branch.
O `/health` retorna 503 se a tabela de professores estiver ausente, para não aprovar
como saudável uma versão que não consegue autenticar.
Voltar à imagem anterior da aplicação não exige apagar a tabela nova.

Criar a primeira conta pelo comando administrativo `php bin/criar-professor.php`.
Ele recebe pela entrada padrão um objeto JSON com `nome`, `email` e `senha`.
Não passar a senha em argumentos, não colocá-la no `.env`, no Git ou no Trello.
O comando grava `password_hash(..., PASSWORD_DEFAULT)` e devolve somente id, nome e email.
Senhas devem ter pelo menos 12 bytes e no máximo 72 bytes, sem bytes nulos. E-mail é normalizado
para minúsculas e deve ser único. Senhas não recebem trim. Não há conta/senha padrão.

Exemplo no **PowerShell 7**, com a senha digitada de forma mascarada:

```powershell
$profNome = Read-Host 'Nome do professor'
$profEmail = Read-Host 'E-mail do professor'
$profSenha = Read-Host 'Senha (12 a 72 bytes)' -MaskInput
try {
    @{ nome = $profNome; email = $profEmail; senha = $profSenha } |
        ConvertTo-Json -Compress |
        docker compose exec -T app php bin/criar-professor.php
} finally {
    Remove-Variable profSenha -ErrorAction SilentlyContinue
}
```

No XAMPP, trocar a última linha do pipeline por `C:\xampp\php\php.exe bin/criar-professor.php`
(usar PHP 8.3 e dependências instaladas). O diretório `bin/` é bloqueado no Apache.
Repetir o comando cria outras contas; ele não redefine senhas nem sobrescreve contas existentes.
O Dockerfile inclui esse comando na imagem final. Recriar a imagem antes de utilizá-lo no Docker.

## Contrato HTTP para o Diogo e para o Insomnia

Todas as URLs respeitam `APP_BASE_PATH`: vazio no Docker ou `/frequencia-facil` no XAMPP.
Usar cookies da mesma origem (`credentials: 'same-origin'` no fetch; cookie jar ativo no Insomnia).
Não guardar a senha, o cookie de sessão ou o token CSRF em localStorage.

1. **GET `/api/auth/sessao`** — público, inicia/consulta a sessão:

   ```json
   {"autenticado": false, "professor": null, "csrfToken": "token-gerado-pelo-servidor"}
   ```

2. **POST `/api/auth/login`** — enviar JSON `{ "email": "...", "senha": "..." }`,
   `Content-Type: application/json` e `X-CSRF-Token` recebido no passo 1.
   Sucesso 200: `{ "autenticado": true, "professor": { "id": 1, "nome": "...", "email": "..." }, "csrfToken": "novo-token" }`.
   O cookie e o token são renovados: **substituir o token em memória** pelo da resposta.
   Credenciais inválidas, conta inexistente, inativa ou temporariamente bloqueada recebem
   a mesma mensagem 401: `E-mail ou senha inválidos.`. Campos inválidos recebem 422.

3. **GET `/api/auth/sessao`** após login — informa o professor atual e o token válido.
   Conta desativada ou sessão expirada volta ao estado anônimo. Resposta com `Cache-Control: no-store`.

4. **APIs de alunos, presenças e histórico** — exigem a sessão em todos os métodos.
   POST, PUT, PATCH e DELETE exigem também `X-CSRF-Token`. A ausência de sessão recebe 401;
   sessão válida com token ausente/incorreto recebe 403. Os contratos de dados permanecem iguais.

5. **POST `/api/auth/logout`** — cookie e `X-CSRF-Token` obrigatórios. Sucesso 204 sem corpo;
   invalida a sessão no servidor e expira o cookie. Apagar o token em memória. GET não faz logout.

As páginas `/`, `/index.php`, `/alunos`, `/chamada` e `/historico` também exigem sessão.
O frontend trata 401 como necessidade de login e mantém a proteção das páginas/APIs.
Um 403 de CSRF deve solicitar nova consulta de sessão; não repetir uma gravação automaticamente.

## Sessão e limites

- Cookie `ff_session`, HttpOnly, SameSite=Lax, somente cookie (sem id na URL), modo estrito.
- Expira após 30 minutos sem atividade ou 8 horas de duração total. Fechar o navegador
  remove o cookie de sessão conforme as opções do navegador; os prazos do servidor continuam valendo.
- O id é renovado no login, e o id anterior deixa de autenticar. Logout invalida o id anterior.
- O professor deve continuar ativo no banco para acessar cada requisição protegida.
- Cinco senhas erradas consecutivas bloqueiam aquela conta por 15 minutos, inclusive ao trocar
  de navegador/sessão. Login bem-sucedido zera o contador. Usar também limite de requisições
  no proxy antes da exposição pública; o bloqueio por conta não substitui proteção contra abuso volumétrico.
- Em `APP_ENV=production`, o cookie é sempre Secure. A URL pública precisa usar **HTTPS**,
  inclusive quando o Nginx termina TLS e encaminha HTTP ao Apache. Não desativar isso para publicar via HTTP.
- A sessão nativa é local ao contêiner: reinício/redeploy pode exigir novo login. Não há
  suporte a múltiplas réplicas sem um armazenamento de sessões compartilhado.

## Testes

Os testes HTTP criam contas aleatórias somente em `frequenciafacil_test`, fazem login,
enviam cookie/CSRF e removem os próprios registros. Nenhuma credencial padrão é incluída.
`AuthApiTest` cobre acesso anônimo, cookies, rotação, logout, CSRF, conta inativa,
tentativas, hash/duplicidade e acesso às páginas após login. Os testes antigos continuam
exercitando CRUD, chamada e histórico autenticados. `AuthValidatorTest` cobre entradas e expiração.
O smoke test espera 401 nas páginas sem login e mantém `/health`, CSS e JS públicos.

Referências: [sessões PHP](https://www.php.net/manual/en/session.security.management.php),
[CSRF OWASP](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html).
