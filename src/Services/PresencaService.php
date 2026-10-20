<?php
namespace App\Services;

use App\Repositories\PresencaRepository;

/**
 * Regras da US02/US03:
 *  - data da aula padrão = hoje
 *  - um registro por aluno por data (novo registro atualiza o anterior)
 *  - histórico com total de presenças e faltas, filtro por período
 */
final class PresencaService
{
    public function __construct(private ?PresencaRepository $repo = null) {}

    private function repo(): PresencaRepository
    {
        return $this->repo ??= new PresencaRepository();
    }

    /** Calcula { presencas, faltas, total } a partir das linhas do histórico. */
    public function totais(array $registros): array
    {
        // TODO(Wendell)
        return ['presencas' => 0, 'faltas' => 0, 'total' => 0];
    }
}
