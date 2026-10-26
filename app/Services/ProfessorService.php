<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use PDO;
use PDOException;

final class ProfessorService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function criar(array $dados): array
    {
        $dados = ProfessorValidator::cadastro($dados);
        try {
            $stmt = $this->db->prepare('INSERT INTO professores (nome, email, senha_hash) VALUES (?, ?, ?)');
            $stmt->execute([$dados['nome'], $dados['email'], password_hash($dados['senha'], PASSWORD_DEFAULT)]);
        } catch (PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
                throw new ApiException('Já existe um professor com este e-mail.', 409);
            }
            throw $error;
        }
        return ['id' => (int) $this->db->lastInsertId(), 'nome' => $dados['nome'], 'email' => $dados['email']];
    }

    public function autenticar(array $dados): array
    {
        $dados = ProfessorValidator::credenciais($dados);
        // A transação serializa tentativas simultâneas da mesma conta.
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT *, (bloqueado_ate > UTC_TIMESTAMP()) AS bloqueado FROM professores WHERE email = ? FOR UPDATE');
            $stmt->execute([$dados['email']]);
            $professor = $stmt->fetch();
            // Mesmo trabalho de hash quando a conta não existe; nunca devolver a causa da recusa.
            $hash = $professor['senha_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
            $valida = password_verify($dados['senha'], $hash);
            if (!$professor || !(bool) $professor['ativo'] || (bool) $professor['bloqueado'] || !$valida) {
                if ($professor && (bool) $professor['ativo'] && !(bool) $professor['bloqueado']) {
                    $tentativas = $professor['bloqueado_ate'] !== null ? 1 : (int) $professor['tentativas'] + 1;
                    $stmt = $this->db->prepare('UPDATE professores SET tentativas = ?, bloqueado_ate = IF(? >= 5, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE), NULL) WHERE id = ?');
                    $stmt->execute([$tentativas, $tentativas, $professor['id']]);
                }
                $this->db->commit();
                throw new ApiException('E-mail ou senha inválidos.', 401);
            }
            $stmt = $this->db->prepare('UPDATE professores SET tentativas = 0, bloqueado_ate = NULL WHERE id = ?');
            $stmt->execute([$professor['id']]);
            $this->db->commit();
            return self::publico($professor);
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function buscarAtivo(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, nome, email FROM professores WHERE id = ? AND ativo = 1');
        $stmt->execute([$id]);
        $professor = $stmt->fetch();
        return $professor ? self::publico($professor) : null;
    }

    private static function publico(array $professor): array
    {
        return ['id' => (int) $professor['id'], 'nome' => $professor['nome'], 'email' => $professor['email']];
    }
}
