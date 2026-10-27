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
        foreach (['DB_HOST', 'DB_PORT', 'MYSQL_DATABASE', 'MYSQL_USER'] as $name) {
            $value = getenv($name);
            if ($value === false || $value === '') {
                throw new RuntimeException('Configuracao do banco incompleta.');
            }
            $config[$name] = $value;
        }

        $config['MYSQL_PASSWORD'] = self::readPassword();

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

    private static function readPassword(): string
    {
        $file = getenv('MYSQL_PASSWORD_FILE');
        if ($file !== false && $file !== '') {
            if (!is_file($file) || is_link($file) || !is_readable($file)) {
                throw new RuntimeException('Arquivo da credencial do banco invalido.');
            }
            $password = file_get_contents($file);
            if ($password === false) {
                throw new RuntimeException('Nao foi possivel ler a credencial do banco.');
            }
            return rtrim($password, "\r\n");
        }

        $password = getenv('MYSQL_PASSWORD');
        if ($password === false) {
            throw new RuntimeException('Configuracao do banco incompleta.');
        }
        return $password;
    }
}
