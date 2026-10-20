<?php
/** Tela: Histórico. Visual em assets/css/estilo.css, comportamento em assets/js/historico.js */
$titulo = 'Histórico';
$menuAtivo = 'historico';
$script = 'historico';
require __DIR__ . '/layout/topo.php';
?>
  <main class="conteudo">
    <div class="titulo-pagina">
      <h1>Histórico</h1>
    </div>

    <div id="mensagem" class="mensagem" role="alert" hidden></div>

    <section class="folha" aria-label="Filtros">
      <form class="campos campos--linha" id="filtros">
        <div class="campo">
          <label class="rotulo" for="aluno">Aluno</label>
          <select class="entrada" id="aluno"><option value="">Escolha um aluno</option></select>
        </div>
        <div class="campo">
          <label class="rotulo" for="inicio">De</label>
          <input class="entrada" id="inicio" type="date">
        </div>
        <div class="campo">
          <label class="rotulo" for="fim">Até</label>
          <input class="entrada" id="fim" type="date">
        </div>
      </form>
      <div class="acoes">
        <button class="botao botao--secundario botao--pequeno" type="button" id="limpar-periodo">Ver todo o período</button>
      </div>
    </section>

    <p class="vazio" id="sem-aluno">Escolha um aluno para ver as presenças e faltas.</p>

    <section class="folha resultado" id="resultado" aria-labelledby="nome-aluno" hidden>
      <div class="resultado-cabecalho">
        <div>
          <h2 id="nome-aluno"></h2>
          <p class="percentual__legenda" id="periodo-texto"></p>
        </div>
        <div>
          <p class="percentual" id="percentual">—</p>
          <p class="percentual__legenda" id="percentual-legenda"></p>
        </div>
      </div>

      <div class="barra-frequencia" id="barra" role="img">
        <div class="barra-frequencia__preenchimento" id="barra-preenchimento"></div>
        <div class="barra-frequencia__minimo" id="barra-minimo"><span>mínimo</span></div>
      </div>

      <dl class="totais">
        <div><dt>Presenças</dt><dd id="total-presencas">0</dd></div>
        <div><dt>Faltas</dt><dd id="total-faltas">0</dd></div>
        <div><dt>Aulas</dt><dd id="total-aulas">0</dd></div>
      </dl>

      <h3>Aula por aula</h3>
      <ol class="caderneta" id="caderneta"></ol>
      <p class="vazio" id="sem-registros" hidden>Nenhuma aula registrada neste período.</p>
    </section>
  </main>
<?php require __DIR__ . '/layout/rodape.php'; ?>
