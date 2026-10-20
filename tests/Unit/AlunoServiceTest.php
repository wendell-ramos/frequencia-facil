<?php
namespace Tests\Unit;

use App\Services\AlunoService;
use PHPUnit\Framework\TestCase;

/**
 * Testes das regras da US01. Marcados como incompletos até o
 * AlunoService::validar() ser implementado — tire o markTestIncomplete e o teste passa a valer.
 */
final class AlunoServiceTest extends TestCase
{
    public function test_nome_e_telefone_sao_obrigatorios(): void
    {
        $this->markTestIncomplete('Aguardando AlunoService::validar() (Wendell)');

        $erros = (new AlunoService())->validar(['nome' => '', 'telefone' => '']);
        $this->assertArrayHasKey('nome', $erros);
        $this->assertArrayHasKey('telefone', $erros);
    }

    public function test_dados_validos_nao_geram_erro(): void
    {
        $this->markTestIncomplete('Aguardando AlunoService::validar() (Wendell)');

        $erros = (new AlunoService())->validar(['nome' => 'Ana', 'telefone' => '(18) 99999-0000']);
        $this->assertSame([], $erros);
    }
}
