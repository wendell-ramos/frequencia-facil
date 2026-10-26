<?php

declare(strict_types=1);

namespace App\Http;

use App\Config\Database;
use App\Services\ProfessorService;
use RuntimeException;

final class AuthSession
{
    public const IDLE_SECONDS = 1800;
    public const MAX_SECONDS = 28800;

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) self::MAX_SECONDS);
        session_name('ff_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => rtrim(getenv('APP_BASE_PATH') ?: '', '/') ?: '/',
            'secure' => getenv('APP_ENV') === 'production' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (!session_start()) {
            throw new RuntimeException('Não foi possível iniciar a sessão.');
        }
        header('Cache-Control: no-store');
        if (isset($_SESSION['professor_id']) && self::expirou($_SESSION, time())) {
            self::renovar();
        }
    }

    public static function expirou(array $sessao, int $agora): bool
    {
        return !isset($sessao['criada_em'], $sessao['atividade_em'])
            || $agora - (int) $sessao['atividade_em'] >= self::IDLE_SECONDS
            || $agora - (int) $sessao['criada_em'] >= self::MAX_SECONDS;
    }

    public static function csrf(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function validarCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!isset($_SESSION['csrf']) || !is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
            throw new ApiException('Token CSRF inválido. Consulte a sessão e tente novamente.', 403);
        }
    }

    public static function entrar(array $professor): void
    {
        self::renovar();
        $_SESSION['professor_id'] = $professor['id'];
        $_SESSION['criada_em'] = $_SESSION['atividade_em'] = time();
    }

    public static function professor(): ?array
    {
        if (!isset($_SESSION['professor_id'])) {
            return null;
        }
        $professor = (new ProfessorService(Database::getConnection()))->buscarAtivo((int) $_SESSION['professor_id']);
        if ($professor === null) {
            self::renovar();
            return null;
        }
        $_SESSION['atividade_em'] = time();
        return $professor;
    }

    public static function exigir(): void
    {
        if (self::professor() === null) {
            throw new ApiException('Autenticação necessária.', 401);
        }
    }

    public static function sair(): void
    {
        $_SESSION = [];
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $cookie['path'], 'secure' => $cookie['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        session_destroy();
    }

    private static function renovar(): void
    {
        $_SESSION = [];
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Não foi possível renovar a sessão.');
        }
        self::csrf();
    }
}
