<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

/** Todo SQL da tabela `presencas` fica aqui. */
final class PresencaRepository
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connection();
    }

    /** Alunos com o status na data (LEFT JOIN: aluno sem registro => presente = null) */
    public function listarPorData(string $data): array
    {
        // TODO(Wendell)
        return [];
    }

    /** INSERT ... ON DUPLICATE KEY UPDATE (chave única aluno_id + data_aula) */
    public function salvar(int $alunoId, string $data, bool $presente): void
    {
        // TODO(Wendell)
    }

    public function historico(int $alunoId, ?string $inicio = null, ?string $fim = null): array
    {
        // TODO(Wendell)
        return [];
    }
}
