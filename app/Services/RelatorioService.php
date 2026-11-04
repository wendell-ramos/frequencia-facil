<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class RelatorioService
{
    private const FREQUENCIA_MINIMA = 75;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function consultar(array $parametros = []): array
    {
        $periodo = HistoricoValidator::periodo($parametros);
        $condicoes = ['p.aluno_id = a.id'];
        $valores = [];
        foreach (['inicio' => '>=', 'fim' => '<='] as $campo => $operador) {
            if ($periodo[$campo] !== null) {
                $condicoes[] = "p.data_aula $operador :$campo";
                $valores[$campo] = $periodo[$campo];
            }
        }

        // Filtrar no JOIN preserva alunos sem marcações no período selecionado.
        $sql = 'SELECT a.id AS alunoId, a.nome, COUNT(p.id) AS total,
                       COALESCE(SUM(p.presente), 0) AS presencas
                FROM alunos a LEFT JOIN presencas p ON ' . implode(' AND ', $condicoes) . '
                GROUP BY a.id, a.nome ORDER BY a.nome, a.id';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($valores);

        $alunos = [];
        foreach ($stmt->fetchAll() as $linha) {
            $total = (int) $linha['total'];
            $presencas = (int) $linha['presencas'];
            $alunos[] = [
                'alunoId' => (int) $linha['alunoId'],
                'nome' => $linha['nome'],
                'presencas' => $presencas,
                'faltas' => $total - $presencas,
                'total' => $total,
                'percentual' => $total === 0 ? null : round($presencas * 100 / $total, 2),
                // Comparar antes de arredondar evita classificar 74,999% como suficiente.
                'baixaFrequencia' => $total > 0 && $presencas * 100 < $total * self::FREQUENCIA_MINIMA,
            ];
        }

        return ['periodo' => $periodo, 'frequenciaMinima' => self::FREQUENCIA_MINIMA, 'alunos' => $alunos];
    }
}
