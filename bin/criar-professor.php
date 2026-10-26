<?php

declare(strict_types=1);

use App\Config\Database;
use App\Config\Environment;
use App\Http\ApiException;
use App\Services\ProfessorService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/vendor/autoload.php';
try {
    Environment::load(dirname(__DIR__) . '/.env');
    // JSON pela entrada padrão: não colocar a senha nos argumentos ou no histórico do terminal.
    $entrada = stream_get_contents(STDIN, 4097);
    if (strlen($entrada) > 4096) {
        throw new RuntimeException('Entrada excede 4096 bytes.');
    }
    $dados = json_decode($entrada, false, 32, JSON_THROW_ON_ERROR);
    if (!$dados instanceof stdClass) {
        throw new RuntimeException('Envie um objeto JSON pela entrada padrão.');
    }
    $professor = (new ProfessorService(Database::getConnection()))->criar((array) $dados);
    fwrite(STDOUT, json_encode($professor, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (ApiException $error) {
    fwrite(STDERR, $error->getMessage() . ' ' . json_encode($error->erros, JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, 'Não foi possível criar o professor. Confira o JSON, a conexão e a migração do banco.' . PHP_EOL);
    exit(1);
}
