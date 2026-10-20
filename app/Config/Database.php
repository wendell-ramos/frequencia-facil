<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use RuntimeException;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $config = [];
        foreach (['DB_HOST', 'DB_PORT', 'MYSQL_DATABASE', 'MYSQL_USER', 'MYSQL_PASSWORD'] as $name) {
            $value = getenv($name);
            if ($value === false || ($value === '' && $name !== 'MYSQL_PASSWORD')) {
                throw new RuntimeException('Configuracao do banco incompleta.');
            }
            $config[$name] = $value;
        }

        $port = filter_var($config['DB_PORT'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        if ($port === false) {
            throw new RuntimeException('Porta do banco invalida.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['DB_HOST'],
            $port,
            $config['MYSQL_DATABASE']
        );

        self::$connection = new PDO($dsn, $config['MYSQL_USER'], $config['MYSQL_PASSWORD'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        return self::$connection;
    }
}
