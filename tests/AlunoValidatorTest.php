<?php

declare(strict_types=1);

use App\Http\ApiException;
use App\Services\AlunoValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AlunoValidatorTest extends TestCase
{
    public function testNormalizaNomeTelefoneENascimentoOpcional(): void
    {
        self::assertSame(
            ['nome' => 'João da Silva', 'telefone' => '(18) 99711-0001', 'dataNascimento' => null],
            AlunoValidator::validate(['nome' => "  João   da Silva  ", 'telefone' => '18997110001'])
        );
    }

    public function testAceitaTelefoneFixoDataBissextaENomeComAcentos(): void
    {
        $aluno = AlunoValidator::validate([
            'nome' => 'José', 'telefone' => '(18) 3711-0001', 'dataNascimento' => '2000-02-29',
        ]);
        self::assertSame('(18) 3711-0001', $aluno['telefone']);
        self::assertSame('2000-02-29', $aluno['dataNascimento']);
    }

    public function testAceitaNomeNoLimiteENascimentoVazio(): void
    {
        $nome = str_repeat('á', 150);
        $aluno = AlunoValidator::validate(['nome' => $nome, 'telefone' => '1837110001', 'dataNascimento' => '']);
        self::assertSame($nome, $aluno['nome']);
        self::assertNull($aluno['dataNascimento']);
    }

    #[DataProvider('dadosInvalidos')]
    public function testRejeitaDadosInvalidos(array $alteracoes, string $campo): void
    {
        try {
            AlunoValidator::validate($alteracoes + ['nome' => 'Ana Souza', 'telefone' => '18997110001']);
            self::fail('Dados inválidos foram aceitos.');
        } catch (ApiException $error) {
            self::assertSame(422, $error->status);
            self::assertArrayHasKey($campo, $error->erros);
        }
    }

    public static function dadosInvalidos(): array
    {
        return [
            'nome vazio' => [['nome' => '  '], 'nome'],
            'nome curto' => [['nome' => 'Ab'], 'nome'],
            'nome extenso' => [['nome' => str_repeat('á', 151)], 'nome'],
            'nome numérico' => [['nome' => 123], 'nome'],
            'nome array' => [['nome' => ['Ana']], 'nome'],
            'nome nulo' => [['nome' => null], 'nome'],
            'telefone vazio' => [['telefone' => ''], 'telefone'],
            'telefone curto' => [['telefone' => '997110001'], 'telefone'],
            'telefone extenso' => [['telefone' => '5518997110001'], 'telefone'],
            'telefone com letras' => [['telefone' => 'abc18997110001'], 'telefone'],
            'telefone numérico' => [['telefone' => 18997110001], 'telefone'],
            'telefone array' => [['telefone' => []], 'telefone'],
            'data impossível' => [['dataNascimento' => '2001-02-29'], 'dataNascimento'],
            'dia impossível' => [['dataNascimento' => '2000-04-31'], 'dataNascimento'],
            'data sem padrão ISO' => [['dataNascimento' => '01/01/2000'], 'dataNascimento'],
            'data não preenchida com zeros' => [['dataNascimento' => '2000-1-1'], 'dataNascimento'],
            'data fora do MySQL' => [['dataNascimento' => '0999-01-01'], 'dataNascimento'],
            'data futura' => [['dataNascimento' => '9999-01-01'], 'dataNascimento'],
            'data array' => [['dataNascimento' => []], 'dataNascimento'],
            'data booleana' => [['dataNascimento' => false], 'dataNascimento'],
        ];
    }

    public function testInformaTodosOsCamposObrigatorios(): void
    {
        try {
            AlunoValidator::validate([]);
            self::fail('Campos obrigatórios ausentes foram aceitos.');
        } catch (ApiException $error) {
            self::assertSame(['nome', 'telefone'], array_keys($error->erros));
        }
    }
}
