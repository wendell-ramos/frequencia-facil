<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\PresencaService;

/**
 * US02 — Registrar presença (item 5) e US03 — Histórico (item 6).
 * Responsável: Wendell. Contrato em docs/api.md.
 */
final class PresencaController
{
    public function __construct(private ?PresencaService $service = null)
    {
        $this->service ??= new PresencaService();
    }

    /** GET /api/presencas?data=AAAA-MM-DD — todos os alunos com o status naquela data */
    public function porData(Request $request): Response
    {
        // TODO(Wendell): data padrão = hoje
        return Response::notImplemented('GET /api/presencas');
    }

    /** POST /api/presencas — grava a chamada do dia (cria ou atualiza) */
    public function registrar(Request $request): Response
    {
        // TODO(Wendell): mesmo aluno + mesma data => atualiza (upsert)
        return Response::notImplemented('POST /api/presencas');
    }

    /** GET /api/alunos/{id}/presencas?inicio=&fim= — histórico + totais */
    public function historico(Request $request): Response
    {
        // TODO(Wendell)
        return Response::notImplemented('GET /api/alunos/{id}/presencas');
    }
}
