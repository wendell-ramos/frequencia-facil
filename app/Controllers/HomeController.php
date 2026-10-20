<?php

declare(strict_types=1);

namespace App\Controllers;

class HomeController
{
    public function index(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        require dirname(__DIR__) . '/Views/home.php';
    }
}
