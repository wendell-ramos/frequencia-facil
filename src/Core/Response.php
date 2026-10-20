<?php
namespace App\Core;

/** Resposta JSON padrão da API. */
final class Response
{
    public function __construct(
        public readonly mixed $data = null,
        public readonly int $status = 200,
    ) {}

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($data, $status);
    }

    /** Erro no formato { "erro": "mensagem", "detalhes": {...} } */
    public static function error(string $mensagem, int $status = 400, array $detalhes = []): self
    {
        $payload = ['erro' => $mensagem];
        if ($detalhes) {
            $payload['detalhes'] = $detalhes;
        }
        return new self($payload, $status);
    }

    public static function notImplemented(string $o_que): self
    {
        return self::error("Endpoint ainda não implementado: {$o_que}", 501);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        if ($this->status !== 204) {
            echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
