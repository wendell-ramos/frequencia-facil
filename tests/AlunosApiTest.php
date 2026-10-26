<?php

declare(strict_types=1);

use App\Config\Database;
use PHPUnit\Framework\TestCase;

/** Integração HTTP + MySQL real. O script de infraestrutura fornece um banco descartável. */
require_once __DIR__ . '/AuthenticatedApiClient.php';

final class AlunosApiTest extends TestCase
{
    use AuthenticatedApiClient;
    private string $baseUrl;
    private PDO $db;
    private array $ids = [];

    protected function setUp(): void
    {
        $url = getenv('FF_TEST_BASE_URL');
        if (!$url) {
            self::markTestSkipped('Execute scripts/test-infrastructure.sh com as imagens de produção e testes.');
        }
        // Nunca aceitar as credenciais do banco normal para os testes de escrita.
        if (getenv('MYSQL_DATABASE') !== 'frequenciafacil_test') {
            self::fail('Os testes HTTP exigem o banco isolado frequenciafacil_test.');
        }
        $this->baseUrl = rtrim($url, '/');
        $this->db = Database::getConnection();
        $this->iniciarAutenticacaoDeTeste();
    }

    protected function tearDown(): void
    {
        $this->removerProfessorDeTeste();
        if (!isset($this->db)) {
            return;
        }
        foreach ($this->ids as $id) {
            $stmt = $this->db->prepare('DELETE FROM presencas WHERE aluno_id = ?');
            $stmt->execute([$id]);
            $stmt = $this->db->prepare('DELETE FROM alunos WHERE id = ?');
            $stmt->execute([$id]);
        }
    }

    private function request(string $method, string $path, ?array $dados = null, ?string $raw = null, string $tipo = 'application/json'): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => "Content-Type: $tipo\r\n" . $this->cabecalhosAutenticacao(),
            'content' => $raw ?? ($dados === null ? '' : json_encode((object) $dados, JSON_THROW_ON_ERROR)),
            'ignore_errors' => true,
            'timeout' => 10,
        ]]);
        $body = file_get_contents($this->baseUrl . $path, false, $context);
        self::assertNotFalse($body, 'Não foi possível acessar a aplicação de teste.');
        $headers = $http_response_header;
        preg_match('/^HTTP\/\S+ (\d+)/', $headers[0], $matches);
        $status = (int) $matches[1];
        $json = $body === '' ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if ($body !== '') {
            self::assertStringContainsString('application/json', implode('\n', $headers));
        }
        return compact('status', 'json', 'headers', 'body');
    }

    private function criar(array $dados = []): array
    {
        $result = $this->request('POST', '/api/alunos', $dados + [
            'nome' => 'Aluno Teste ' . bin2hex(random_bytes(6)),
            'telefone' => '(18) 99711-0001',
            'dataNascimento' => '2000-02-29',
        ]);
        if (isset($result['json']['id'])) {
            $this->ids[] = $result['json']['id'];
        }
        self::assertSame(201, $result['status'], $result['body']);
        return $result;
    }

    public function testCicloCompletoPersisteNoMySQL(): void
    {
        $novo = $this->criar(['nome' => 'Ana Teste', 'telefone' => '18997110001']);
        $aluno = $novo['json'];
        self::assertIsInt($aluno['id']);
        self::assertSame('(18) 99711-0001', $aluno['telefone']);
        self::assertArrayHasKey('criadoEm', $aluno);
        self::assertStringContainsString('/api/alunos/' . $aluno['id'], implode('\n', $novo['headers']));

        $consulta = $this->request('GET', '/api/alunos/' . $aluno['id']);
        self::assertSame(200, $consulta['status']);
        self::assertSame($aluno, $consulta['json']);
        $lista = $this->request('GET', '/api/alunos?busca=ana');
        self::assertSame(200, $lista['status']);
        self::assertSame([$aluno], $lista['json']);

        $dados = ['nome' => 'Ana Atualizada', 'telefone' => '(18) 3711-0002', 'dataNascimento' => null];
        $editado = $this->request('PUT', '/api/alunos/' . $aluno['id'], $dados);
        self::assertSame(200, $editado['status']);
        self::assertSame('Ana Atualizada', $editado['json']['nome']);
        self::assertNull($editado['json']['dataNascimento']);
        $semMudanca = $this->request('PUT', '/api/alunos/' . $aluno['id'], $dados);
        self::assertSame(200, $semMudanca['status']);

        $stmt = $this->db->prepare('SELECT nome, telefone, data_nascimento FROM alunos WHERE id = ?');
        $stmt->execute([$aluno['id']]);
        self::assertSame(['nome' => 'Ana Atualizada', 'telefone' => '(18) 3711-0002', 'data_nascimento' => null], $stmt->fetch());

        $excluido = $this->request('DELETE', '/api/alunos/' . $aluno['id']);
        self::assertSame(204, $excluido['status']);
        self::assertSame('', $excluido['body']);
        $stmt->execute([$aluno['id']]);
        self::assertFalse($stmt->fetch());
        self::assertSame([], $this->request('GET', '/api/alunos')['json']);
    }

    public function testDuplicidadeIgnoraMascaraECaixaNaCriacaoEEdicao(): void
    {
        $this->criar(['nome' => 'Ana Souza']);
        $duplicado = $this->request('POST', '/api/alunos', ['nome' => ' ana souza ', 'telefone' => '18997110001']);
        self::assertSame(409, $duplicado['status']);
        self::assertArrayHasKey('mensagem', $duplicado['json']);

        $outro = $this->criar(['nome' => 'Outro Aluno'])['json'];
        $editado = $this->request('PUT', '/api/alunos/' . $outro['id'], ['nome' => 'Ana Souza', 'telefone' => '18997110001']);
        self::assertSame(409, $editado['status']);
        self::assertSame('Outro Aluno', $this->request('GET', '/api/alunos/' . $outro['id'])['json']['nome']);
        // Mesmo nome com outro telefone é permitido.
        $this->criar(['nome' => 'Ana Souza', 'telefone' => '18997110002']);
    }

    public function testDuplicidadeReconheceTelefoneAntigoSemMascara(): void
    {
        $stmt = $this->db->prepare('INSERT INTO alunos (nome, telefone) VALUES (?, ?)');
        $stmt->execute(['Aluno Antigo', '18997110001']);
        $this->ids[] = (int) $this->db->lastInsertId();
        $response = $this->request('POST', '/api/alunos', ['nome' => 'Aluno Antigo', 'telefone' => '(18) 99711-0001']);
        self::assertSame(409, $response['status']);
    }

    public function testExclusaoComHistoricoRetornaConflitoEPreservaDados(): void
    {
        $id = $this->criar()['json']['id'];
        $stmt = $this->db->prepare('INSERT INTO presencas (aluno_id, data_aula, presente) VALUES (?, ?, ?)');
        $stmt->execute([$id, '2026-01-01', 1]);
        $response = $this->request('DELETE', '/api/alunos/' . $id);
        self::assertSame(409, $response['status']);
        self::assertStringContainsString('presenças registradas', $response['json']['mensagem']);
        self::assertSame(200, $this->request('GET', '/api/alunos/' . $id)['status']);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM presencas WHERE aluno_id = ?');
        $stmt->execute([$id]);
        self::assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testValidacoesRetornam422SemGravar(): void
    {
        $response = $this->request('POST', '/api/alunos', []);
        self::assertSame(422, $response['status']);
        self::assertSame(['nome', 'telefone'], array_keys($response['json']['erros']));
        $response = $this->request('POST', '/api/alunos', ['nome' => [], 'telefone' => 123, 'dataNascimento' => '2001-02-29']);
        self::assertSame(422, $response['status']);
        self::assertCount(3, $response['json']['erros']);
        self::assertSame([], $this->request('GET', '/api/alunos')['json']);
    }

    public function testJsonInvalidoETipoDeConteudo(): void
    {
        foreach (['{', '[]', 'null', '"texto"'] as $raw) {
            self::assertSame(400, $this->request('POST', '/api/alunos', raw: $raw)['status']);
        }
        self::assertSame(415, $this->request('POST', '/api/alunos', raw: 'nome=Ana', tipo: 'application/x-www-form-urlencoded')['status']);
    }

    public function testEdicaoInvalidaPreservaAlunoExistente(): void
    {
        $aluno = $this->criar()['json'];
        $response = $this->request('PUT', '/api/alunos/' . $aluno['id'], ['nome' => 'Ab', 'telefone' => '123']);
        self::assertSame(422, $response['status']);
        self::assertSame($aluno, $this->request('GET', '/api/alunos/' . $aluno['id'])['json']);
    }

    public function testIdsInexistentesRotasEMetodos(): void
    {
        $id = $this->criar()['json']['id'];
        self::assertSame(204, $this->request('DELETE', '/api/alunos/' . $id)['status']);
        foreach (['GET', 'PUT', 'DELETE'] as $method) {
            self::assertSame(404, $this->request($method, '/api/alunos/' . $id, ['nome' => 'Ana', 'telefone' => '18997110001'])['status']);
        }
        foreach (['0', '4294967296', '99999999999999999999', 'abc', '1/rota-inexistente'] as $invalid) {
            self::assertSame(404, $this->request('GET', '/api/alunos/' . $invalid)['status']);
        }
        $response = $this->request('PATCH', '/api/alunos/1');
        self::assertSame(405, $response['status']);
        self::assertStringContainsString('Allow: GET, PUT, DELETE', implode('\n', $response['headers']));
        $response = $this->request('DELETE', '/api/alunos');
        self::assertSame(405, $response['status']);
        self::assertStringContainsString('Allow: GET, POST', implode('\n', $response['headers']));
    }

    public function testBuscaLiteralOrdenacaoEParametrosInvalidos(): void
    {
        $this->criar(['nome' => 'Zeca Teste']);
        $ana = $this->criar(['nome' => 'Ana 100%_ Teste'])['json'];
        $alunos = $this->request('GET', '/api/alunos')['json'];
        self::assertSame(['Ana 100%_ Teste', 'Zeca Teste'], array_column($alunos, 'nome'));
        self::assertSame([$ana], $this->request('GET', '/api/alunos?busca=' . rawurlencode('%_'))['json']);
        self::assertSame([], $this->request('GET', '/api/alunos?busca=' . rawurlencode("' OR 1=1 --"))['json']);
        self::assertSame(422, $this->request('GET', '/api/alunos?busca[]=ana')['status']);
        self::assertSame(422, $this->request('GET', '/api/alunos?busca=' . str_repeat('a', 151))['status']);
    }
}
