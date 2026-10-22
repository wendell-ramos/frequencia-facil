<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use PDO;
use PDOException;
use Throwable;

final class PresencaService
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function consultar(string $data): array
    {
        $data = PresencaValidator::data($data);
        $stmt = $this->connection->prepare(
            'SELECT a.id AS alunoId, a.nome, p.presente FROM alunos a '
            . 'LEFT JOIN presencas p ON p.aluno_id = a.id AND p.data_aula = :data ORDER BY a.nome, a.id'
        );
        $stmt->execute(['data' => $data]);
        $alunos = array_map(static function (array $aluno): array {
            $aluno['alunoId'] = (int) $aluno['alunoId'];
            $aluno['presente'] = $aluno['presente'] === null ? null : (bool) $aluno['presente'];
            return $aluno;
        }, $stmt->fetchAll());
        return ['data' => $data, 'alunos' => $alunos];
    }

    public function salvar(array $dados): array
    {
        $chamada = PresencaValidator::validate($dados);
        // Processar os alunos na mesma ordem reduz disputas entre chamadas simultâneas.
        usort($chamada['registros'], static fn (array $a, array $b): int => $a['alunoId'] <=> $b['alunoId']);
        $this->connection->beginTransaction();
        try {
            $stmt = $this->connection->prepare(
                'INSERT INTO presencas (aluno_id, data_aula, presente) VALUES (:alunoId, :data, :presente) '
                . 'ON DUPLICATE KEY UPDATE presente = :atualizado'
            );
            foreach ($chamada['registros'] as $registro) {
                $stmt->execute([
                    'alunoId' => $registro['alunoId'],
                    'data' => $chamada['data'],
                    'presente' => (int) $registro['presente'],
                    'atualizado' => (int) $registro['presente'],
                ]);
            }
            $this->connection->commit();
        } catch (Throwable $error) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1452) {
                throw new ApiException('A chamada contém um aluno que não existe. Nenhum registro foi salvo.', 422, [
                    'registros' => 'Confira os IDs dos alunos e envie a chamada novamente.',
                ]);
            }
            throw $error;
        }
        return ['mensagem' => 'Presença salva', 'total' => count($chamada['registros'])];
    }
}
