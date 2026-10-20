<?php

declare(strict_types=1);

use App\Controllers\PaginaController;
use PHPUnit\Framework\TestCase;

final class PaginasTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('APP_BASE_PATH');
    }

    private function capturar(callable $exibir): string
    {
        ob_start();
        $exibir();
        return (string) ob_get_clean();
    }

    private function view(string $arquivo): string
    {
        return $this->capturar(function () use ($arquivo): void {
            require dirname(__DIR__) . "/app/Views/{$arquivo}.php";
        });
    }

    public function testTodasAsTelasCarregamLayoutEScript(): void
    {
        $telas = [
            'home' => $this->view('home'),
            'alunos' => $this->capturar(fn () => (new PaginaController())->alunos()),
            'chamada' => $this->capturar(fn () => (new PaginaController())->chamada()),
            'historico' => $this->capturar(fn () => (new PaginaController())->historico()),
        ];
        $scripts = ['home' => 'inicio', 'alunos' => 'alunos', 'chamada' => 'chamada', 'historico' => 'historico'];

        foreach ($telas as $nome => $html) {
            self::assertStringContainsString('<main class="conteudo">', $html, $nome);
            self::assertStringContainsString('/assets/css/estilo.css', $html, $nome);
            self::assertStringContainsString("/assets/js/{$scripts[$nome]}.js", $html, $nome);
        }
    }

    public function testMenuMarcaATelaAtual(): void
    {
        $html = $this->capturar(fn () => (new PaginaController())->alunos());
        self::assertStringContainsString('href="/alunos" aria-current="page"', $html);
        self::assertStringNotContainsString('href="/chamada" aria-current="page"', $html);
    }

    public function testLinksRespeitamOBasePath(): void
    {
        putenv('APP_BASE_PATH=/frequencia-facil');
        $html = $this->view('home');
        self::assertStringContainsString('data-base="/frequencia-facil"', $html);
        self::assertStringContainsString('href="/frequencia-facil/chamada"', $html);
        self::assertStringContainsString('src="/frequencia-facil/assets/js/inicio.js"', $html);
    }
}
