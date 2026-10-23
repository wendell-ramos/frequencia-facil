<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class HistoricoService
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function consultar(int $id, array $parametros = []): array
    {
        $filtros = HistoricoValidator::periodo($parametros);
        $aluno = (new AlunoService($this->connection))->buscar($id);
        $sql = 'SELECT data_aula AS dataAula, presente FROM presencas WHERE aluno_id = :id';
        $valores = ['id' => $id];
        if ($filtros['inicio'] !== null) {
            $sql .= ' AND data_aula >= :inicio';
            $valores['inicio'] = $filtros['inicio'];
        }
        if ($filtros['fim'] !== null) {
            $sql .= ' AND data_aula <= :fim';
            $valores['fim'] = $filtros['fim'];
        }
        $sql .= ' ORDER BY data_aula DESC';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($valores);

        $registros = [];
        $presencas = 0;
        foreach ($stmt->fetchAll() as $registro) {
            $registro['presente'] = (bool) $registro['presente'];
            $presencas += (int) $registro['presente'];
            $registros[] = $registro;
        }
        // Totais e lista usam os mesmos registros, inclusive ao filtrar por período.
        $total = count($registros);
        return [
            'aluno' => ['id' => $aluno['id'], 'nome' => $aluno['nome']],
            'totais' => ['presencas' => $presencas, 'faltas' => $total - $presencas, 'total' => $total],
            'registros' => $registros,
        ];
    }
}
