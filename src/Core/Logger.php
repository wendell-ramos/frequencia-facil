<?php
namespace App\Core;

/**
 * Log estruturado (uma linha JSON por evento).
 * Escreve em stderr (aparece no `docker compose logs app`) e em logs/app.log.
 * Faz parte do monitoramento básico exigido na atividade.
 */
final class Logger
{
    public static function info(string $msg, array $ctx = []): void    { self::write('INFO', $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::write('WARNING', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void   { self::write('ERROR', $msg, $ctx); }

    private static function write(string $level, string $msg, array $ctx): void
    {
        $line = json_encode([
            'time'    => date('c'),
            'level'   => $level,
            'message' => $msg,
            'context' => $ctx,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        @file_put_contents('php://stderr', $line);
        $file = Config::get('log')['file'] ?? null;
        if ($file && is_writable(dirname($file))) {
            @file_put_contents($file, $line, FILE_APPEND);
        }
    }
}
