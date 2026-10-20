<?php
namespace App\Repositories;

use App\Core\Database;
use App\Models\Aluno;
use PDO;

/** Todo SQL da tabela `alunos` fica aqui. Use sempre prepared statements. */
final class AlunoRepository
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connection();
    }

    /** @return Aluno[] */
    public function listar(?string $busca = null): array
    {
        // TODO(Wendell)
        return [];
    }

    public function buscarPorId(int $id): ?Aluno
    {
        // TODO(Wendell)
        return null;
    }

    public function existeNomeTelefone(string $nome, string $telefone, ?int $ignorarId = null): bool
    {
        // TODO(Wendell): usado para impedir duplicado (critério da US01)
        return false;
    }

    public function inserir(Aluno $aluno): Aluno
    {
        // TODO(Wendell)
        return $aluno;
    }

    public function atualizar(Aluno $aluno): void
    {
        // TODO(Wendell)
    }

    public function excluir(int $id): void
    {
        // TODO(Wendell)
    }
}
