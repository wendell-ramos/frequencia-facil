<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;

final class ProfessorValidator
{
    public static function credenciais(array $dados): array
    {
        $email = is_string($dados['email'] ?? null) ? strtolower(trim($dados['email'])) : '';
        $senha = is_string($dados['senha'] ?? null) ? $dados['senha'] : '';
        $erros = [];
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros['email'] = 'Informe um e-mail válido.';
        }
        if ($senha === '' || strlen($senha) > 72 || str_contains($senha, "\0")) {
            $erros['senha'] = 'Informe a senha (até 72 bytes).';
        }
        if ($erros !== []) {
            throw new ApiException('Dados inválidos.', 422, $erros);
        }
        return ['email' => $email, 'senha' => $senha];
    }

    public static function cadastro(array $dados): array
    {
        $valores = self::credenciais($dados);
        $nome = is_string($dados['nome'] ?? null) ? trim($dados['nome']) : '';
        $erros = [];
        if (!preg_match('/^.{3,150}$/us', $nome)) {
            $erros['nome'] = 'Informe um nome entre 3 e 150 caracteres.';
        }
        if (strlen($valores['senha']) < 12) {
            $erros['senha'] = 'Use uma senha com pelo menos 12 bytes e até 72 bytes.';
        }
        if ($erros !== []) {
            throw new ApiException('Dados inválidos.', 422, $erros);
        }
        return ['nome' => $nome] + $valores;
    }
}
