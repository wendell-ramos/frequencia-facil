<?php
/** Tela: Entrar (pública). Comportamento em assets/js/login.js */
$titulo = 'Entrar';
$menuAtivo = null;
$script = 'login';
$semMenu = true;
require __DIR__ . '/layout/topo.php';
?>
  <main class="conteudo conteudo--login">
    <section class="folha login" aria-labelledby="titulo-login">
      <h1 class="login__titulo" id="titulo-login">Entrar</h1>
      <p class="login__apoio">Acesso do professor ao diário de classe.</p>

      <div id="mensagem" class="mensagem" role="alert" hidden></div>

      <form id="formulario-login" novalidate>
        <div class="campos">
          <div class="campo">
            <label class="rotulo" for="email">E-mail</label>
            <input class="entrada" id="email" name="email" type="email" autocomplete="username" required aria-describedby="erro-email">
            <span class="erro-campo" id="erro-email" data-erro="email"></span>
          </div>
          <div class="campo">
            <label class="rotulo" for="senha">Senha</label>
            <div class="senha">
              <input class="entrada" id="senha" name="senha" type="password" autocomplete="current-password" required aria-describedby="erro-senha">
              <button class="senha__mostrar" type="button" id="mostrar-senha" aria-pressed="false">Mostrar</button>
            </div>
            <span class="erro-campo" id="erro-senha" data-erro="senha"></span>
          </div>
        </div>
        <div class="acoes">
          <button class="botao login__botao" type="submit" id="botao-entrar">Entrar</button>
        </div>
      </form>
    </section>
  </main>
<?php require __DIR__ . '/layout/rodape.php'; ?>
