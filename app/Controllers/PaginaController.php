<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Telas do sistema (mesmo padrão do HomeController, que cuida da página inicial).
 * As views só têm o HTML; os dados vêm da API em JSON, buscados pelo JavaScript
 * em assets/js (contrato em docs/api.md).
 */
class PaginaController
{
    /** Tela pública de login (a única tela que não exige sessão). */
    public function login(): void
    {
        $this->exibir('login');
    }

    public function alunos(): void
    {
        $this->exibir('alunos');
    }

    public function chamada(): void
    {
        $this->exibir('chamada');
    }

    public function historico(): void
    {
        $this->exibir('historico');
    }

    private function exibir(string $view): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        require dirname(__DIR__) . "/Views/{$view}.php";
    }
}
