<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AlunoService;

/**
 * US01 — Cadastro de alunos (itens 2 e 3 do backlog).
 * Responsável: Wendell. Contrato de entrada/saída em docs/api.md.
 *
 * Regra: o controller só lê a requisição e devolve a resposta.
 * Validação e regras de negócio ficam no AlunoService; SQL fica no AlunoRepository.
 */
final class AlunoController
{
    public function __construct(private ?AlunoService $service = null)
    {
        $this->service ??= new AlunoService();
    }

    /** GET /api/alunos — lista todos (aceita ?busca=nome) */
    public function index(Request $request): Response
    {
        // TODO(Wendell)
        return Response::notImplemented('GET /api/alunos');
    }

    /** GET /api/alunos/{id} */
    public function show(Request $request): Response
    {
        // TODO(Wendell): 404 se não existir
        return Response::notImplemented('GET /api/alunos/{id}');
    }

    /** POST /api/alunos — 201 criado | 422 dados inválidos | 409 duplicado */
    public function store(Request $request): Response
    {
        // TODO(Wendell)
        return Response::notImplemented('POST /api/alunos');
    }

    /** PUT /api/alunos/{id} */
    public function update(Request $request): Response
    {
        // TODO(Wendell)
        return Response::notImplemented('PUT /api/alunos/{id}');
    }

    /** DELETE /api/alunos/{id} — 204 sem corpo */
    public function destroy(Request $request): Response
    {
        // TODO(Wendell)
        return Response::notImplemented('DELETE /api/alunos/{id}');
    }
}
