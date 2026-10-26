<?php

declare(strict_types=1);

use App\Services\ProfessorService;

trait AuthenticatedApiClient
{
    private string $cookieAuth = '';
    private string $csrfAuth = '';
    private ?int $professorTesteId = null;
    private string $emailTeste;
    private string $senhaTeste;

    private function iniciarAutenticacaoDeTeste(): void
    {
        $this->emailTeste = 'teste-' . bin2hex(random_bytes(8)) . '@example.test';
        $this->senhaTeste = bin2hex(random_bytes(16));
        $professor = (new ProfessorService($this->db))->criar(['nome' => 'Professor Teste', 'email' => $this->emailTeste, 'senha' => $this->senhaTeste]);
        $this->professorTesteId = $professor['id'];
        $bootstrap = $this->authRequest('GET', '/api/auth/sessao');
        self::assertSame(200, $bootstrap['status']);
        $this->csrfAuth = $bootstrap['json']['csrfToken'];
        $login = $this->authRequest('POST', '/api/auth/login', ['email' => $this->emailTeste, 'senha' => $this->senhaTeste]);
        self::assertSame(200, $login['status']);
        $this->csrfAuth = $login['json']['csrfToken'];
    }

    private function removerProfessorDeTeste(): void
    {
        if ($this->professorTesteId !== null) {
            $this->db->prepare('DELETE FROM professores WHERE id = ?')->execute([$this->professorTesteId]);
        }
    }

    private function cabecalhosAutenticacao(): string
    {
        return 'Cookie: ff_session=' . $this->cookieAuth . "\r\nX-CSRF-Token: " . $this->csrfAuth . "\r\n";
    }

    private function authRequest(string $method, string $path, ?array $dados = null, ?string $csrf = null, ?string $cookie = null, ?string $raw = null, string $tipo = 'application/json'): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => 'Content-Type: ' . $tipo . "\r\nCookie: ff_session=" . ($cookie ?? $this->cookieAuth) . "\r\nX-CSRF-Token: " . ($csrf ?? $this->csrfAuth) . "\r\n",
            'content' => $raw ?? ($dados === null ? '' : json_encode((object) $dados, JSON_THROW_ON_ERROR)),
            'ignore_errors' => true,
            'timeout' => 10,
        ]]);
        $body = file_get_contents($this->baseUrl . $path, false, $context);
        self::assertNotFalse($body);
        $headers = $http_response_header;
        preg_match('/^HTTP\/\S+ (\d+)/', $headers[0], $matches);
        foreach ($headers as $header) {
            if ($cookie === null && preg_match('/^Set-Cookie: ff_session=([^;]*)/i', $header, $parts)) {
                $this->cookieAuth = $parts[1];
            }
        }
        return ['status' => (int) $matches[1], 'json' => $body === '' ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR), 'headers' => $headers];
    }
}
