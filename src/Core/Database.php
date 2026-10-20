<?php
namespace App\Core;

use PDO;

/**
 * Conexão única com o MySQL (PDO).
 * Uso: $pdo = Database::connection();
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('db');
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /** Permite trocar a conexão nos testes de integração (ex.: SQLite em memória). */
    public static function setConnection(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}
