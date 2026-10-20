<?php
namespace App\Core;

final class Config
{
    private static ?array $config = null;

    public static function get(string $key): mixed
    {
        self::$config ??= require __DIR__ . '/../../config/config.php';
        return self::$config[$key] ?? null;
    }
}
