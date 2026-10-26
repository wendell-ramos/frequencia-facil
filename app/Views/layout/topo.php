<?php
/**
 * Início de todas as páginas: <head>, cabeçalho e menu.
 * Cada view define antes de incluir: $titulo, $menuAtivo (ou null) e $script.
 * A tela de login define também $semMenu = true (não há menu nem botão Sair).
 */
$semMenu ??= false;
// Base do sistema: vazia no Docker, /frequencia-facil no XAMPP (ver .env.example)
$base = htmlspecialchars(rtrim(getenv('APP_BASE_PATH') ?: '', '/'), ENT_QUOTES, 'UTF-8');

$itensMenu = [
    'chamada' => 'Chamada',
    'alunos' => 'Alunos',
    'historico' => 'Histórico',
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $titulo ?> · FrequênciaFácil</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&family=Bitter:wght@600;700&display=swap">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/estilo.css">
</head>
<body data-base="<?= $base ?>">
  <header class="cabecalho">
    <div class="cabecalho__interno">
      <a class="marca" href="<?= $base ?>/">FrequênciaFácil</a>
      <?php if (!$semMenu): ?>
      <nav class="navegacao" aria-label="Principal">
        <?php foreach ($itensMenu as $rota => $nome): ?>
          <a href="<?= $base ?>/<?= $rota ?>"<?= $rota === $menuAtivo ? ' aria-current="page"' : '' ?>><?= $nome ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="sessao">
        <span class="sessao__nome" id="nome-professor"></span>
        <button class="sessao__sair" type="button" id="botao-sair">Sair</button>
      </div>
      <?php endif; ?>
    </div>
  </header>
