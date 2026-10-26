<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;

final class HistoricoValidator
{
    public static function periodo(array $parametros): array
    {
        $filtros = ['inicio' => null, 'fim' => null];
        $erros = [];
        foreach (array_keys($filtros) as $campo) {
            $data = $parametros[$campo] ?? null;
            // A tela envia inicio=&fim= ao consultar todo o histórico.
            if ($data === null || $data === '') {
                continue;
            }
            if (!is_string($data)
                || !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $data, $partes)
                || (int) $partes[1] < 1000
                || !checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
                $erros[$campo] = 'Informe uma data válida no formato AAAA-MM-DD.';
            } else {
                $filtros[$campo] = $data;
            }
        }
        if ($erros === [] && $filtros['inicio'] !== null && $filtros['fim'] !== null && $filtros['inicio'] > $filtros['fim']) {
            $erros['fim'] = 'A data final deve ser igual ou posterior à data inicial.';
        }
        if ($erros !== []) {
            throw new ApiException('Período inválido.', 422, $erros);
        }
        return $filtros;
    }
}
