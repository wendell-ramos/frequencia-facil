# Telas (frontend)

Responsável: Diogo.

```text
app/Controllers/HomeController.php     /  (página inicial, do Wendell)
app/Controllers/PaginaController.php   /alunos, /chamada, /historico
app/Views/
├── layout/topo.php      <head>, cabeçalho, menu, nome do professor e botão Sair
├── login.php            /login      Card #10: e-mail e senha (pública, sem menu)
├── layout/rodape.php    Rodapé e o <script> da tela
├── home.php             /           Data de hoje e situação da chamada
├── alunos.php           /alunos     US01: cadastro, lista, editar, excluir
├── chamada.php          /chamada    US02: presença por data, caixas P e F
└── historico.php        /historico  US03: histórico, totais e % de frequência
assets/
├── css/estilo.css       Todo o visual (classes em português: bloco__elemento--variacao)
└── js/
    ├── api.js           Chamadas à API + modo de demonstração por recurso (MODO_DEMONSTRACAO)
    ├── ui.js            Funções comuns (mensagens, datas, máscara de telefone, url())
    ├── login.js         Entrar e voltar para a tela pedida (?volta=)
    ├── inicio.js  alunos.js  chamada.js  historico.js
```

## Como funciona

- Para criar uma tela nova: adicione o caminho em `$rotas` e um `elseif` no `index.php`,
  um método no `PaginaController`, a view em `app/Views/` e o script em `assets/js/`.
- Cada view define `$titulo`, `$menuAtivo` e `$script` e inclui `layout/topo.php` e `layout/rodape.php`.
- As views só têm o HTML. Os dados vêm da API em JSON, buscados pelo JavaScript
  (contrato em `docs/api.md`).
- As telas usam a API real (`/api/alunos`, `/api/presencas`, `/api/alunos/{id}/presencas`).
- **Login e sessão** ([autenticacao.md](autenticacao.md)):
  - `/login` é a única tela pública. Abrir outra tela sem sessão **pelo navegador**
    (`Accept: text/html`) redireciona para `/login?volta=<tela>`; depois de entrar, volta para ela.
    Chamadas de API e o curl dos testes continuam recebendo o 401 em JSON.
  - `api.js` guarda o token CSRF **só em memória** (nunca em localStorage), busca em
    `GET /api/auth/sessao` quando precisa, troca pelo token novo do login e envia `X-CSRF-Token`
    em POST/PUT/DELETE. O cookie `ff_session` é HttpOnly e fica com o navegador.
  - 401 em qualquer chamada leva ao login (sessão expirou). 403 descarta o token e mostra
    "Confira os dados e tente salvar de novo", sem repetir a gravação sozinho.
  - O botão **Sair** chama `POST /api/auth/logout` e volta para o login.
- Em `assets/js/api.js`, `MODO_DEMONSTRACAO` permite trocar cada recurso para dados de
  demonstração salvos no navegador (útil para testar uma tela sem banco). Com algum recurso
  em demonstração, o rodapé avisa.
- Sem conexão com o servidor, as telas mostram "Não foi possível conectar ao servidor".
- Links e chamadas à API respeitam o `APP_BASE_PATH`: o `layout/topo.php` grava a base em
  `<body data-base="...">` e o JavaScript usa `url('/caminho')`.
- O `.htaccess` libera só a pasta `assets/` para o Apache servir direto;
  `app/` continua bloqueada.

## Visual

Inspirado no diário de classe: verde de quadro-negro no cabeçalho e nos botões,
presença em azul ("caneta azul") e falta em vermelho ("caneta vermelha").
Fontes: Bitter (títulos e marcas P/F) e Atkinson Hyperlegible (texto), do Google Fonts.
Sem internet, o navegador usa fontes parecidas do sistema.
