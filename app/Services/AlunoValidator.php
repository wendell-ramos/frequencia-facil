<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use DateTimeImmutable;
use DateTimeZone;

final class AlunoValidator
{
    public static function validate(array $dados): array
    {
        $erros = [];
        $nome = is_string($dados['nome'] ?? null)
            ? trim(preg_replace('/\s+/u', ' ', $dados['nome']) ?? '') : '';
        if ($nome === '') {
            $erros['nome'] = 'Informe o nome.';
        } elseif (!preg_match('/^.{3,150}$/us', $nome)) {
            $erros['nome'] = 'O nome deve ter entre 3 e 150 caracteres.';
        }

        $telefone = is_string($dados['telefone'] ?? null) ? trim($dados['telefone']) : '';
        $digitos = preg_replace('/\D/', '', $telefone);
        if ($telefone === '') {
            $erros['telefone'] = 'Informe o telefone.';
        } elseif (!preg_match('/^[0-9()\s-]+$/D', $telefone) || !preg_match('/^[0-9]{10,11}$/D', $digitos)) {
            $erros['telefone'] = 'Informe um telefone com DDD e 10 ou 11 dígitos.';
        }

        $nascimento = $dados['dataNascimento'] ?? null;
        if ($nascimento === '') {
            $nascimento = null;
        }
        if ($nascimento !== null) {
            $valida = is_string($nascimento)
                && preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $nascimento, $partes)
                && (int) $partes[1] >= 1000
                && checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]);
            if (!$valida) {
                $erros['dataNascimento'] = 'Informe uma data válida no formato AAAA-MM-DD.';
            } elseif ($nascimento > (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d')) {
                $erros['dataNascimento'] = 'A data de nascimento não pode ser no futuro.';
            }
        }

        if ($erros !== []) {
            throw new ApiException('Dados inválidos.', 422, $erros);
        }

        // Um formato único também permite que a chave UNIQUE proteja gravações simultâneas.
        $telefone = '(' . substr($digitos, 0, 2) . ') '
            . substr($digitos, 2, -4) . '-' . substr($digitos, -4);

        return ['nome' => $nome, 'telefone' => $telefone, 'dataNascimento' => $nascimento];
    }
}
