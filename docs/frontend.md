# Telas (frontend)

Responsável: Diogo.

```text
app/Controllers/HomeController.php     /  (página inicial, do Wendell)
app/Controllers/PaginaController.php   /alunos, /chamada, /historico
app/Views/
├── layout/topo.php      <head>, cabeçalho e menu (comum a todas)
├── layout/rodape.php    Rodapé e o <script> da tela
├── home.php             /           Data de hoje e situação da chamada
├── alunos.php           /alunos     US01: cadastro, lista, editar, excluir
├── chamada.php          /chamada    US02: presença por data, caixas P e F
└── historico.php        /historico  US03: histórico, totais e % de frequência
assets/
├── css/estilo.css       Todo o visual (classes em português: bloco__elemento--variacao)
└── js/
    ├── api.js           Chamadas à API + modo de demonstração (USAR_MOCK)
    ├── ui.js            Funções comuns (mensagens, datas, máscara de telefone, url())
    ├── inicio.js  alunos.js  chamada.js  historico.js
```

## Como funciona

- Para criar uma tela nova: adicione o caminho em `$rotas` e um `elseif` no `index.php`,
  um método no `PaginaController`, a view em `app/Views/` e o script em `assets/js/`.
- Cada view define `$titulo`, `$menuAtivo` e `$script` e inclui `layout/topo.php` e `layout/rodape.php`.
- As views só têm o HTML. Os dados vêm da API em JSON, buscados pelo JavaScript
  (contrato em `docs/api.md`).
- `USAR_MOCK = true` em `assets/js/api.js`: dados de demonstração salvos no navegador,
  com as mesmas regras da API. Troque para `false` quando os endpoints estiverem prontos.
- Links e chamadas à API respeitam o `APP_BASE_PATH`: o `layout/topo.php` grava a base em
  `<body data-base="...">` e o JavaScript usa `url('/caminho')`.
- O `.htaccess` libera só a pasta `assets/` para o Apache servir direto;
  `app/` continua bloqueada.

## Visual

Inspirado no diário de classe: verde de quadro-negro no cabeçalho e nos botões,
presença em azul ("caneta azul") e falta em vermelho ("caneta vermelha").
Fontes: Bitter (títulos e marcas P/F) e Atkinson Hyperlegible (texto), do Google Fonts.
Sem internet, o navegador usa fontes parecidas do sistema.
