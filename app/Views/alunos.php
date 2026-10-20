<?php
/** Tela: Alunos. Visual em assets/css/estilo.css, comportamento em assets/js/alunos.js */
$titulo = 'Alunos';
$menuAtivo = 'alunos';
$script = 'alunos';
require __DIR__ . '/layout/topo.php';
?>
  <main class="conteudo">
    <div class="titulo-pagina">
      <h1>Alunos</h1>
      <p class="titulo-pagina__apoio" id="contagem" role="status"></p>
    </div>

    <div id="mensagem" class="mensagem" role="alert" hidden></div>

    <div class="pagina-alunos">
      <section class="folha pagina-alunos__formulario" id="bloco-formulario" aria-labelledby="titulo-formulario">
        <h2 class="folha__titulo" id="titulo-formulario">Novo aluno</h2>
        <form id="formulario" novalidate>
          <input type="hidden" id="id">
          <div class="campos">
            <div class="campo">
              <label class="rotulo" for="nome">Nome</label>
              <input class="entrada" id="nome" name="nome" autocomplete="off" maxlength="120" aria-describedby="erro-nome">
              <span class="erro-campo" id="erro-nome" data-erro="nome"></span>
            </div>
            <div class="campo">
              <label class="rotulo" for="telefone">Telefone</label>
              <input class="entrada" id="telefone" name="telefone" inputmode="tel" placeholder="(18) 99999-0000" aria-describedby="erro-telefone">
              <span class="erro-campo" id="erro-telefone" data-erro="telefone"></span>
            </div>
            <div class="campo">
              <label class="rotulo" for="dataNascimento">Data de nascimento <span class="rotulo__opcional">(opcional)</span></label>
              <input class="entrada" id="dataNascimento" name="dataNascimento" type="date" aria-describedby="erro-dataNascimento">
              <span class="erro-campo" id="erro-dataNascimento" data-erro="dataNascimento"></span>
            </div>
          </div>
          <div class="acoes">
            <button class="botao" type="submit" id="botao-salvar">Cadastrar aluno</button>
            <button class="botao botao--secundario" type="button" id="botao-cancelar" hidden>Cancelar edição</button>
          </div>
        </form>
      </section>

      <section class="folha pagina-alunos__lista" aria-labelledby="titulo-lista">
        <h2 class="visualmente-oculto" id="titulo-lista">Lista de alunos</h2>
        <div class="campo busca">
          <label class="rotulo" for="busca">Buscar por nome</label>
          <input class="entrada" id="busca" type="search" placeholder="Digite parte do nome">
        </div>
        <ul class="lista-alunos" id="lista" aria-live="polite"></ul>
      </section>
    </div>

    <dialog class="dialogo" id="dialogo-exclusao" aria-labelledby="dialogo-titulo">
      <form method="dialog">
        <h2 id="dialogo-titulo">Excluir aluno?</h2>
        <p class="dialogo__texto" id="dialogo-texto"></p>
        <div class="acoes">
          <button class="botao botao--perigo-cheio" value="confirmar">Excluir aluno</button>
          <button class="botao botao--secundario" value="cancelar" autofocus>Cancelar</button>
        </div>
      </form>
    </dialog>
  </main>
<?php require __DIR__ . '/layout/rodape.php'; ?>
