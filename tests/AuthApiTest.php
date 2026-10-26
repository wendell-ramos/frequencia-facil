<?php

declare(strict_types=1);

use App\Config\Database;
use App\Services\ProfessorService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AuthenticatedApiClient.php';

final class AuthApiTest extends TestCase
{
    use AuthenticatedApiClient;

    private string $baseUrl;
    private PDO $db;

    protected function setUp(): void
    {
        if (!getenv('FF_TEST_BASE_URL')) {
            self::markTestSkipped('Execute a integração com MySQL temporário.');
        }
        if (getenv('MYSQL_DATABASE') !== 'frequenciafacil_test') {
            self::fail('A autenticação deve ser testada somente no banco descartável.');
        }
        $this->baseUrl = rtrim(getenv('FF_TEST_BASE_URL'), '/');
        $this->db = Database::getConnection();
        $this->iniciarAutenticacaoDeTeste();
    }

    protected function tearDown(): void
    {
        $this->removerProfessorDeTeste();
    }

    public function testAnonimoNaoLeNemGravaDadosENaoAbreTelas(): void
    {
        foreach (['/api/alunos', '/api/alunos/1', '/api/alunos/1/presencas', '/api/presencas', '/', '/alunos', '/chamada', '/historico'] as $rota) {
            self::assertSame(401, $this->authRequest('GET', $rota, cookie: '')['status'], $rota);
        }
        foreach (['POST', 'PUT', 'DELETE'] as $metodo) {
            self::assertSame(401, $this->authRequest($metodo, '/api/alunos/1', [], cookie: '')['status']);
        }
        self::assertSame(401, $this->authRequest('POST', '/api/presencas', [], cookie: '')['status']);
        self::assertSame(200, $this->authRequest('GET', '/health', cookie: '')['status']);
    }

    public function testSessaoExpõeSomenteDadosPublicosECookieProtegido(): void
    {
        $r = $this->authRequest('GET', '/api/auth/sessao');
        self::assertTrue($r['json']['autenticado']);
        self::assertSame(['id' => $this->professorTesteId, 'nome' => 'Professor Teste', 'email' => $this->emailTeste], $r['json']['professor']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $r['json']['csrfToken']);
        self::assertStringContainsString('Cache-Control: no-store', implode('\n', $r['headers']));
        $anonimo = $this->authRequest('GET', '/api/auth/sessao', cookie: 'id-fixado-pelo-cliente');
        self::assertFalse($anonimo['json']['autenticado']);
        self::assertNull($anonimo['json']['professor']);
        $headers = strtolower(implode('\n', $anonimo['headers']));
        self::assertStringContainsString('httponly', $headers);
        self::assertStringContainsString('samesite=lax', $headers);
        self::assertStringNotContainsString('ff_session=id-fixado-pelo-cliente', $headers);
    }

    public function testLoginRotacionaIdETokenENaoReaproveitaSessaoAnterior(): void
    {
        $anterior = $this->cookieAuth;
        $tokenAnterior = $this->csrfAuth;
        $r = $this->authRequest('POST', '/api/auth/login', ['email' => strtoupper($this->emailTeste), 'senha' => $this->senhaTeste]);
        self::assertSame(200, $r['status']);
        self::assertNotSame($anterior, $this->cookieAuth);
        self::assertNotSame($tokenAnterior, $r['json']['csrfToken']);
        self::assertSame(401, $this->authRequest('GET', '/api/alunos', cookie: $anterior)['status']);
        self::assertSame(403, $this->authRequest('POST', '/api/alunos', [], csrf: $tokenAnterior)['status']);
        $this->csrfAuth = $r['json']['csrfToken'];
        self::assertSame(422, $this->authRequest('POST', '/api/alunos', [])['status']);
    }

    public function testCsrfObrigatorioParaLoginLogoutEGravacoes(): void
    {
        foreach (['', 'token-incorreto'] as $token) {
            foreach ([['POST', '/api/auth/login'], ['POST', '/api/auth/logout'], ['POST', '/api/alunos'], ['PUT', '/api/alunos/1'], ['DELETE', '/api/alunos/1'], ['POST', '/api/presencas']] as [$metodo, $rota]) {
                self::assertSame(403, $this->authRequest($metodo, $rota, [], csrf: $token)['status'], $rota);
            }
        }
        self::assertSame(200, $this->authRequest('GET', '/api/alunos')['status']);
    }

    public function testLogoutRevogaCookieAntigoEDemandaNovoLogin(): void
    {
        $antigo = $this->cookieAuth;
        self::assertSame(204, $this->authRequest('POST', '/api/auth/logout')['status']);
        self::assertSame(401, $this->authRequest('GET', '/api/alunos', cookie: $antigo)['status']);
        $r = $this->authRequest('GET', '/api/auth/sessao');
        self::assertFalse($r['json']['autenticado']);
        $this->csrfAuth = $r['json']['csrfToken'];
        self::assertSame(200, $this->authRequest('POST', '/api/auth/login', ['email' => $this->emailTeste, 'senha' => $this->senhaTeste])['status']);
    }

    public function testCredenciaisErradasContaInexistenteEInativaTemMesmaResposta(): void
    {
        $errado = $this->authRequest('POST', '/api/auth/login', ['email' => $this->emailTeste, 'senha' => 'senha-incorreta']);
        $ausente = $this->authRequest('POST', '/api/auth/login', ['email' => 'nao-existe@example.test', 'senha' => $this->senhaTeste]);
        $this->db->prepare('UPDATE professores SET ativo = 0 WHERE id = ?')->execute([$this->professorTesteId]);
        $inativo = $this->authRequest('POST', '/api/auth/login', ['email' => $this->emailTeste, 'senha' => $this->senhaTeste]);
        foreach ([$errado, $ausente, $inativo] as $r) {
            self::assertSame(401, $r['status']);
            self::assertSame(['mensagem' => 'E-mail ou senha inválidos.'], $r['json']);
        }
        self::assertSame(401, $this->authRequest('GET', '/api/alunos')['status']);
    }

    public function testCincoFalhasBloqueiamContaEntreSessoesEDepoisLibera(): void
    {
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(401, $this->authRequest('POST', '/api/auth/login', ['email' => $this->emailTeste, 'senha' => 'incorreta'])['status']);
        }
        $this->cookieAuth = '';
        $this->csrfAuth = $this->authRequest('GET', '/api/auth/sessao')['json']['csrfToken'];
        self::assertSame(401, $this->authRequest('POST', '/api/auth/login', ['email' => $this->emailTeste, 'senha' => $this->senhaTeste])['status']);
        $this->db->prepare('UPDATE professores SET bloqueado_ate = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE id = ?')->execute([$this->professorTesteId]);
        self::assertSame(200, $this->authRequest('POST', '/api/auth/login', ['email' => $this->emailTeste, 'senha' => $this->senhaTeste])['status']);
    }

    public function testSenhaArmazenadaComHashEDuplicidadeImpedida(): void
    {
        $stmt = $this->db->prepare('SELECT senha_hash FROM professores WHERE id = ?');
        $stmt->execute([$this->professorTesteId]);
        $hash = $stmt->fetchColumn();
        self::assertNotSame($this->senhaTeste, $hash);
        self::assertTrue(password_verify($this->senhaTeste, $hash));
        $this->expectException(\App\Http\ApiException::class);
        (new ProfessorService($this->db))->criar(['nome' => 'Outro Professor', 'email' => strtoupper($this->emailTeste), 'senha' => $this->senhaTeste]);
    }

    public function testMetodosInvalidosENenhumCadastroPublico(): void
    {
        foreach ([['GET', 'login'], ['GET', 'logout'], ['POST', 'sessao']] as [$metodo, $rota]) {
            self::assertSame(405, $this->authRequest($metodo, '/api/auth/' . $rota)['status']);
        }
        self::assertSame(404, $this->authRequest('POST', '/api/auth/cadastro', [])['status']);
        self::assertSame(422, $this->authRequest('POST', '/api/auth/login', ['email' => [], 'senha' => []])['status']);
    }

    public function testTelasAutenticadasContinuamAcessiveis(): void
    {
        foreach (['/' => 'Fazer a chamada de hoje', '/alunos' => '<h1>Alunos</h1>', '/chamada' => '<h1>Chamada</h1>', '/historico' => '<h1>Hist'] as $rota => $conteudo) {
            $context = stream_context_create(['http' => ['header' => $this->cabecalhosAutenticacao(), 'ignore_errors' => true, 'timeout' => 10]]);
            $html = file_get_contents($this->baseUrl . $rota, false, $context);
            self::assertNotFalse($html);
            self::assertStringContainsString('200', $http_response_header[0]);
            self::assertStringContainsString($conteudo, $html);
        }
    }

    public function testLoginRejeitaJsonInvalidoTipoErradoECorpoGrande(): void
    {
        foreach (['[]', 'null', '{invalido'] as $raw) {
            self::assertSame(400, $this->authRequest('POST', '/api/auth/login', raw: $raw)['status']);
        }
        self::assertSame(415, $this->authRequest('POST', '/api/auth/login', raw: 'email=teste', tipo: 'application/x-www-form-urlencoded')['status']);
        self::assertSame(413, $this->authRequest('POST', '/api/auth/login', raw: str_repeat('a', 4097))['status']);
    }

    public function testHealthNaoAprovaDeploySemMigracaoDeProfessores(): void
    {
        $this->db->exec('RENAME TABLE professores TO professores_health_test');
        try {
            self::assertSame(503, $this->authRequest('GET', '/health', cookie: '')['status']);
        } finally {
            $this->db->exec('RENAME TABLE professores_health_test TO professores');
        }
        self::assertSame(200, $this->authRequest('GET', '/health', cookie: '')['status']);
    }
}
