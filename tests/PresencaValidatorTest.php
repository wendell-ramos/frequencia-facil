<?php

declare(strict_types=1);

use App\Http\ApiException;
use App\Services\PresencaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PresencaValidatorTest extends TestCase
{
    public function testAceitaPresenteAusenteEDiaBissexto(): void
    {
        $dados = ['data' => '2000-02-29', 'registros' => [
            ['alunoId' => 1, 'presente' => true], ['alunoId' => 2, 'presente' => false],
        ]];
        self::assertSame($dados, PresencaValidator::validate($dados));
    }

    public function testAceitaObjetosDoJsonESomenteOsCamposDoContrato(): void
    {
        $dados = ['data' => '2000-01-01', 'registros' => [(object) ['alunoId' => 1, 'presente' => false, 'nome' => 'Ignorado']]];
        self::assertSame([['alunoId' => 1, 'presente' => false]], PresencaValidator::validate($dados)['registros']);
    }

    public function testHojeUsaOFusoDoBrasil(): void
    {
        self::assertSame((new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d'), PresencaValidator::hoje());
        self::assertSame(PresencaValidator::hoje(), PresencaValidator::data(PresencaValidator::hoje()));
    }

    #[DataProvider('datasInvalidas')]
    public function testRejeitaDataInvalida(mixed $data): void
    {
        try {
            PresencaValidator::data($data);
            self::fail('Data inválida foi aceita.');
        } catch (ApiException $error) {
            self::assertSame(422, $error->status);
            self::assertArrayHasKey('data', $error->erros);
        }
    }

    public static function datasInvalidas(): array
    {
        return array_map(static fn ($valor) => [$valor], [null, '', [], 20000101, false, '2001-02-29', '2000-04-31', '01/01/2000', '2000-1-1', '0999-01-01', '9999-01-01']);
    }

    #[DataProvider('registrosInvalidos')]
    public function testRejeitaRegistrosInvalidos(mixed $registros, string $campo): void
    {
        try {
            PresencaValidator::validate(['data' => '2000-01-01', 'registros' => $registros]);
            self::fail('Registros inválidos foram aceitos.');
        } catch (ApiException $error) {
            self::assertSame(422, $error->status);
            self::assertArrayHasKey($campo, $error->erros);
        }
    }

    public static function registrosInvalidos(): array
    {
        return [
            'ausentes' => [null, 'registros'],
            'lista vazia' => [[], 'registros'],
            'texto' => ['alunos', 'registros'],
            'objeto' => [(object) ['alunoId' => 1], 'registros'],
            'mapa' => [['aluno' => ['alunoId' => 1, 'presente' => true]], 'registros'],
            'registro escalar' => [[true], 'registros.0'],
            'id ausente' => [[['presente' => true]], 'registros.0.alunoId'],
            'id texto' => [[['alunoId' => '1', 'presente' => true]], 'registros.0.alunoId'],
            'id decimal' => [[['alunoId' => 1.0, 'presente' => true]], 'registros.0.alunoId'],
            'id zero' => [[['alunoId' => 0, 'presente' => true]], 'registros.0.alunoId'],
            'id negativo' => [[['alunoId' => -1, 'presente' => true]], 'registros.0.alunoId'],
            'id fora do banco' => [[['alunoId' => 4294967296, 'presente' => true]], 'registros.0.alunoId'],
            'presente ausente' => [[['alunoId' => 1]], 'registros.0.presente'],
            'presente nulo' => [[['alunoId' => 1, 'presente' => null]], 'registros.0.presente'],
            'presente texto' => [[['alunoId' => 1, 'presente' => 'false']], 'registros.0.presente'],
            'presente inteiro' => [[['alunoId' => 1, 'presente' => 0]], 'registros.0.presente'],
            'id repetido' => [[['alunoId' => 1, 'presente' => true], ['alunoId' => 1, 'presente' => false]], 'registros.1.alunoId'],
        ];
    }
}
