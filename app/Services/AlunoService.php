<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use PDO;
use PDOException;

final class AlunoService
{
    private const CAMPOS = 'id, nome, telefone, data_nascimento AS dataNascimento, criado_em AS criadoEm';

    public function __construct(private readonly PDO $connection)
    {
    }

    public function listar(string $busca = ''): array
    {
        // Trata % e _ como texto, sem transformar a busca em um padrão arbitrário de LIKE.
        $termo = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busca) . '%';
        $stmt = $this->connection->prepare(
            'SELECT ' . self::CAMPOS . " FROM alunos WHERE nome LIKE :busca ESCAPE '!' ORDER BY nome, id"
        );
        $stmt->execute(['busca' => $termo]);
        return array_map($this->formatar(...), $stmt->fetchAll());
    }

    public function buscar(int $id): array
    {
        $stmt = $this->connection->prepare('SELECT ' . self::CAMPOS . ' FROM alunos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $aluno = $stmt->fetch();
        if ($aluno === false) {
            throw new ApiException('Aluno não encontrado.', 404);
        }
        return $this->formatar($aluno);
    }

    public function criar(array $dados): array
    {
        $aluno = AlunoValidator::validate($dados);
        $this->verificarDuplicidade($aluno);
        try {
            $stmt = $this->connection->prepare(
                'INSERT INTO alunos (nome, telefone, data_nascimento) VALUES (:nome, :telefone, :dataNascimento)'
            );
            $stmt->execute($aluno);
        } catch (PDOException $error) {
            $this->tratarConflito($error);
        }
        return $this->buscar((int) $this->connection->lastInsertId());
    }

    public function atualizar(int $id, array $dados): array
    {
        $this->buscar($id);
        $aluno = AlunoValidator::validate($dados);
        $this->verificarDuplicidade($aluno, $id);
        try {
            $stmt = $this->connection->prepare(
                'UPDATE alunos SET nome = :nome, telefone = :telefone, data_nascimento = :dataNascimento WHERE id = :id'
            );
            $stmt->execute($aluno + ['id' => $id]);
        } catch (PDOException $error) {
            $this->tratarConflito($error);
        }
        // Um PUT sem mudanças também é sucesso; rowCount() pode retornar zero nesse caso.
        return $this->buscar($id);
    }

    public function excluir(int $id): void
    {
        try {
            $stmt = $this->connection->prepare('DELETE FROM alunos WHERE id = :id');
            $stmt->execute(['id' => $id]);
        } catch (PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) === 1451) {
                throw new ApiException('Não é possível excluir: o aluno já tem presenças registradas.', 409);
            }
            throw $error;
        }
        if ($stmt->rowCount() === 0) {
            throw new ApiException('Aluno não encontrado.', 404);
        }
    }

    private function verificarDuplicidade(array $aluno, int $idAtual = 0): void
    {
        // Também reconhece telefones antigos gravados sem máscara no banco.
        $stmt = $this->connection->prepare(
            "SELECT id FROM alunos WHERE nome = :nome AND REGEXP_REPLACE(telefone, '[^0-9]', '') = :telefone AND id <> :id LIMIT 1"
        );
        $stmt->execute([
            'nome' => $aluno['nome'],
            'telefone' => preg_replace('/\D/', '', $aluno['telefone']),
            'id' => $idAtual,
        ]);
        if ($stmt->fetch() !== false) {
            throw new ApiException('Já existe um aluno com este nome e telefone.', 409);
        }
    }

    private function tratarConflito(PDOException $error): never
    {
        if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
            throw new ApiException('Já existe um aluno com este nome e telefone.', 409);
        }
        throw $error;
    }

    private function formatar(array $aluno): array
    {
        $aluno['id'] = (int) $aluno['id'];
        return $aluno;
    }
}
