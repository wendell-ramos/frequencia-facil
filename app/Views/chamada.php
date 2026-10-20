<?php
/** Tela: Chamada. Visual em assets/css/estilo.css, comportamento em assets/js/chamada.js */
$titulo = 'Chamada';
$menuAtivo = 'chamada';
$script = 'chamada';
require __DIR__ . '/layout/topo.php';
?>
  <main class="conteudo">
    <div class="titulo-pagina">
      <h1>Chamada</h1>
      <p class="titulo-pagina__apoio" id="data-extenso"></p>
    </div>

    <div id="mensagem" class="mensagem" role="alert" hidden></div>

    <div class="seletor-data">
      <button class="botao botao--secundario botao-icone" type="button" id="dia-anterior" aria-label="Dia anterior">‹</button>
      <div class="campo">
        <label class="rotulo" for="data">Data da aula</label>
        <input class="entrada" id="data" type="date">
      </div>
      <button class="botao botao--secundario botao-icone" type="button" id="dia-seguinte" aria-label="Dia seguinte">›</button>
      <button class="botao botao--secundario" type="button" id="ir-hoje">Hoje</button>
    </div>

    <div class="situacao-chamada">
      <p class="contadores" id="contadores" aria-live="polite"></p>
      <button class="botao botao--secundario botao--pequeno" type="button" id="todos-presentes">Marcar todos como presentes</button>
    </div>

    <ol class="lista-chamada" id="lista"></ol>

    <div class="barra-salvar" id="barra-salvar">
      <span class="barra-salvar__estado" id="estado-salvar">Nada alterado</span>
      <button class="botao" type="button" id="botao-salvar">Salvar chamada</button>
    </div>
  </main>
<?php require __DIR__ . '/layout/rodape.php'; ?>
