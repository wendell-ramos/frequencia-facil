<?php
// Todas as rotas da API ficam aqui. O contrato completo está em docs/api.md.

use App\Controllers\AlunoController;
use App\Controllers\HealthController;
use App\Controllers\PresencaController;
use App\Core\Router;

return function (Router $r): void {
    // Monitoramento (usado pelo CD e pelo Docker)
    $r->get('/api/health', [HealthController::class, 'check']);

    // US01 — Alunos (itens 2 e 3 do backlog)
    $r->get('/api/alunos',           [AlunoController::class, 'index']);
    $r->get('/api/alunos/{id}',      [AlunoController::class, 'show']);
    $r->post('/api/alunos',          [AlunoController::class, 'store']);
    $r->put('/api/alunos/{id}',      [AlunoController::class, 'update']);
    $r->delete('/api/alunos/{id}',   [AlunoController::class, 'destroy']);

    // US02 — Registrar presença (item 5)
    $r->get('/api/presencas',        [PresencaController::class, 'porData']);
    $r->post('/api/presencas',       [PresencaController::class, 'registrar']);

    // US03 — Histórico de presença (item 6)
    $r->get('/api/alunos/{id}/presencas', [PresencaController::class, 'historico']);
};
