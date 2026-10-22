<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        string $mensagem,
        public readonly int $status,
        public readonly array $erros = [],
    ) {
        parent::__construct($mensagem);
    }
}
