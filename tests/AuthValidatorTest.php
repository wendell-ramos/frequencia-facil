<?php

declare(strict_types=1);

use App\Http\ApiException;
use App\Http\AuthSession;
use App\Services\ProfessorValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthValidatorTest extends TestCase
{
    public function testCadastroNormalizaEmailEPreservaSenha(): void
    {
        self::assertSame(['nome' => 'Professor Teste', 'email' => 'prof@example.test', 'senha' => '  SenhaLonga123  '], ProfessorValidator::cadastro(['nome' => ' Professor Teste ', 'email' => ' PROF@example.test ', 'senha' => '  SenhaLonga123  ']));
    }

    #[DataProvider('dadosInvalidos')]
    public function testCadastroRejeitaDadosInvalidos(array $dados): void
    {
        $this->expectException(ApiException::class);
        ProfessorValidator::cadastro($dados);
    }

    public static function dadosInvalidos(): array
    {
        $base = ['nome' => 'Professor', 'email' => 'prof@example.test', 'senha' => 'SenhaLonga12345'];
        $casos = [[[]]];
        foreach (['nome' => ['', 'Ab', [], str_repeat('a', 151)], 'email' => ['', [], 'sem-arroba', str_repeat('a', 255) . '@a.test'], 'senha' => ['', [], 'curta', str_repeat('a', 73)]] as $campo => $valores) {
            foreach ($valores as $valor) {
                $casos[] = [array_replace($base, [$campo => $valor])];
            }
        }
        $casos[] = [array_replace($base, ['senha' => "SenhaLonga\0Invalida"])];
        return $casos;
    }

    #[DataProvider('limitesSessao')]
    public function testLimitesDeExpiracao(array $sessao, int $agora, bool $expirou): void
    {
        self::assertSame($expirou, AuthSession::expirou($sessao, $agora));
    }

    public static function limitesSessao(): array
    {
        return [
            [[], 100, true],
            [['criada_em' => 100, 'atividade_em' => 100], 1899, false],
            [['criada_em' => 100, 'atividade_em' => 100], 1900, true],
            [['criada_em' => 100, 'atividade_em' => 28899], 28899, false],
            [['criada_em' => 100, 'atividade_em' => 28900], 28900, true],
        ];
    }
}
