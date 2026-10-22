<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use DateTimeImmutable;
use DateTimeZone;
use stdClass;

final class PresencaValidator
{
    public static function hoje(): string
    {
        return (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    }

    public static function data(mixed $data): string
    {
        if (!is_string($data)
            || !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $data, $partes)
            || (int) $partes[1] < 1000
            || !checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            throw new ApiException('Data inválida.', 422, ['data' => 'Informe uma data válida no formato AAAA-MM-DD.']);
        }
        if ($data > self::hoje()) {
            throw new ApiException('Data inválida.', 422, ['data' => 'A data da chamada não pode ser no futuro.']);
        }
        return $data;
    }

    public static function validate(array $dados): array
    {
        $data = self::data($dados['data'] ?? null);
        $registros = $dados['registros'] ?? null;
        if (!is_array($registros) || !array_is_list($registros) || $registros === []) {
            throw new ApiException('Dados inválidos.', 422, ['registros' => 'Informe uma lista com pelo menos um registro.']);
        }

        $erros = [];
        $ids = [];
        $validados = [];
        foreach ($registros as $indice => $registro) {
            if ($registro instanceof stdClass) {
                $registro = (array) $registro;
            }
            if (!is_array($registro)) {
                $erros["registros.$indice"] = 'Informe um objeto com alunoId e presente.';
                continue;
            }
            $id = $registro['alunoId'] ?? null;
            if (!is_int($id) || $id < 1 || $id > 4294967295) {
                $erros["registros.$indice.alunoId"] = 'Informe um ID de aluno inteiro e positivo.';
            } elseif (isset($ids[$id])) {
                $erros["registros.$indice.alunoId"] = 'O aluno aparece mais de uma vez nesta chamada.';
            } else {
                $ids[$id] = true;
            }
            if (!is_bool($registro['presente'] ?? null)) {
                $erros["registros.$indice.presente"] = 'Informe true para presente ou false para ausente.';
            }
            $validados[] = ['alunoId' => $id, 'presente' => $registro['presente'] ?? null];
        }
        if ($erros !== []) {
            throw new ApiException('Dados inválidos.', 422, $erros);
        }
        return ['data' => $data, 'registros' => $validados];
    }
}
