<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use Throwable;

/**
 * GET /api/health — diz se a aplicação e o banco estão de pé.
 * Usado pelo HEALTHCHECK do Docker e pelo último passo do CD.
 */
final class HealthController
{
    public function check(Request $request): Response
    {
        try {
            Database::connection()->query('SELECT 1');
            $db = 'ok';
        } catch (Throwable) {
            $db = 'indisponivel';
        }

        return Response::json([
            'status'   => $db === 'ok' ? 'ok' : 'degradado',
            'banco'    => $db,
            'horario'  => date('c'),
            'versao'   => getenv('APP_VERSION') ?: 'dev',
        ], $db === 'ok' ? 200 : 503);
    }
}
