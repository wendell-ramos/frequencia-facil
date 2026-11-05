<?php
/** Tela: Relatório de frequência. Visual em assets/css/estilo.css, comportamento em assets/js/relatorio.js */
$titulo = 'Relatório';
$menuAtivo = 'relatorio';
$script = 'relatorio';
require __DIR__ . '/layout/topo.php';
?>
  <main class="conteudo">
    <div class="titulo-pagina">
      <h1>Relatório de frequência</h1>
    </div>

    <section class="folha" aria-label="Filtros">
      <form class="campos campos--linha" id="filtros" novalidate>
        <div class="campo">
          <label class="rotulo" for="inicio">De <span class="rotulo__opcional">(opcional)</span></label>
          <input class="entrada" id="inicio" name="inicio" type="date" aria-describedby="erro-inicio">
          <span class="erro-campo" id="erro-inicio"></span>
        </div>
        <div class="campo">
          <label class="rotulo" for="fim">Até <span class="rotulo__opcional">(opcional)</span></label>
          <input class="entrada" id="fim" name="fim" type="date" aria-describedby="erro-fim">
          <span class="erro-campo" id="erro-fim"></span>
        </div>
      </form>
      <div class="acoes">
        <button class="botao" type="submit" form="filtros" id="consultar">Consultar</button>
        <button class="botao botao--secundario" type="button" id="limpar-periodo">Limpar período e ver tudo</button>
      </div>
    </section>

    <div id="mensagem" class="mensagem" role="alert" hidden></div>
    <div class="mensagem-acao" id="erro-acao" hidden>
      <button class="botao botao--secundario" type="button" id="tentar-novamente">Tentar novamente</button>
    </div>

    <p class="vazio" id="carregando" role="status" hidden>Carregando relatório…</p>

    <section class="folha resultado" id="resultado" aria-labelledby="titulo-resultado" aria-live="polite" hidden>
      <h2 id="titulo-resultado">Frequência por aluno</h2>
      <p class="percentual__legenda" id="periodo-texto"></p>
      <p class="percentual__legenda" id="minimo-texto"></p>

      <p class="vazio" id="sem-alunos" hidden>Nenhum aluno cadastrado.</p>

      <table class="relatorio" id="tabela" hidden>
        <thead>
          <tr>
            <th scope="col">Aluno</th>
            <th scope="col" class="relatorio__num">Presenças</th>
            <th scope="col" class="relatorio__num">Faltas</th>
            <th scope="col" class="relatorio__num">Total de aulas</th>
            <th scope="col" class="relatorio__num">Frequência</th>
          </tr>
        </thead>
        <tbody id="linhas"></tbody>
      </table>
    </section>
  </main>
<?php require __DIR__ . '/layout/rodape.php'; ?>
