<?php
/** Tela: Início. Visual em assets/css/estilo.css, comportamento em assets/js/inicio.js */
$titulo = 'Início';
$menuAtivo = null;
$script = 'inicio';
require __DIR__ . '/layout/topo.php';
?>
  <main class="conteudo">
    <section class="abertura" aria-labelledby="data-hoje">
      <h1 class="abertura__data" id="data-hoje">Hoje</h1>
      <p class="abertura__situacao" id="situacao" role="status">Carregando a turma…</p>
      <a class="botao botao--grande" id="botao-principal" href="<?= $base ?>/chamada">Fazer a chamada de hoje</a>
    </section>

    <nav class="atalhos" aria-label="Outras telas">
      <a class="atalho" href="<?= $base ?>/alunos">
        <span class="atalho__nome">Alunos</span>
        <span class="atalho__descricao">Cadastre a turma e mantenha os contatos em dia.</span>
      </a>
      <a class="atalho" href="<?= $base ?>/historico">
        <span class="atalho__nome">Histórico</span>
        <span class="atalho__descricao">Veja presenças, faltas e a frequência de cada aluno.</span>
      </a>
    </nav>
  </main>
<?php require __DIR__ . '/layout/rodape.php'; ?>
