<?php

declare(strict_types=1);

use App\Http\ApiException;
use App\Services\HistoricoValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HistoricoValidatorTest extends TestCase
{
    #[DataProvider('periodosValidos')]
    public function testAceitaFiltrosOpcionais(array $entrada, array $esperado): void
    {
        self::assertSame($esperado, HistoricoValidator::periodo($entrada));
    }

    public static function periodosValidos(): array
    {
        return [
            'sem filtros' => [[], ['inicio' => null, 'fim' => null]],
            'vazios enviados pela tela' => [['inicio' => '', 'fim' => ''], ['inicio' => null, 'fim' => null]],
            'nulos' => [['inicio' => null, 'fim' => null], ['inicio' => null, 'fim' => null]],
            'somente início' => [['inicio' => '2000-02-29'], ['inicio' => '2000-02-29', 'fim' => null]],
            'somente fim' => [['fim' => '2000-03-01'], ['inicio' => null, 'fim' => '2000-03-01']],
            'mesmo dia' => [['inicio' => '2000-02-29', 'fim' => '2000-02-29'], ['inicio' => '2000-02-29', 'fim' => '2000-02-29']],
            'limites de consulta futuros' => [['inicio' => '9999-01-01', 'fim' => '9999-12-31'], ['inicio' => '9999-01-01', 'fim' => '9999-12-31']],
        ];
    }

    #[DataProvider('datasInvalidas')]
    public function testRejeitaDatasInvalidas(string $campo, mixed $valor): void
    {
        try {
            HistoricoValidator::periodo([$campo => $valor]);
            self::fail('Filtro inválido foi aceito.');
        } catch (ApiException $error) {
            self::assertSame(422, $error->status);
            self::assertArrayHasKey($campo, $error->erros);
        }
    }

    public static function datasInvalidas(): array
    {
        $casos = [];
        foreach (['inicio', 'fim'] as $campo) {
            foreach ([[], (object) [], true, 20000101, '2001-02-29', '2000-04-31', '2000-1-1', '01/01/2000', '0999-01-01', '2000-01-01 ', "2000-01-01' OR 1=1 --"] as $indice => $valor) {
                $casos["$campo $indice"] = [$campo, $valor];
            }
        }
        return $casos;
    }

    public function testRejeitaPeriodoInvertido(): void
    {
        try {
            HistoricoValidator::periodo(['inicio' => '2000-02-02', 'fim' => '2000-02-01']);
            self::fail('Período invertido foi aceito.');
        } catch (ApiException $error) {
            self::assertSame(422, $error->status);
            self::assertArrayHasKey('fim', $error->erros);
        }
    }

    public function testInformaOsDoisCamposInvalidos(): void
    {
        try {
            HistoricoValidator::periodo(['inicio' => 'errado', 'fim' => []]);
            self::fail('Datas inválidas foram aceitas.');
        } catch (ApiException $error) {
            self::assertSame(['inicio', 'fim'], array_keys($error->erros));
        }
    }
}
