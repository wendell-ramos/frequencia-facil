<?php

declare(strict_types=1);

use App\Config\Database;
use App\Services\PresencaValidator;
use PHPUnit\Framework\TestCase;

final class PresencasApiTest extends TestCase
{
    private string $baseUrl;
    private PDO $db;
    private array $ids = [];

    protected function setUp(): void
    {
        $url = getenv('FF_TEST_BASE_URL');
        if (!$url) {
            self::markTestSkipped('Execute scripts/test-infrastructure.sh com as imagens de produção e testes.');
        }
        if (getenv('MYSQL_DATABASE') !== 'frequenciafacil_test') {
            self::fail('Os testes HTTP exigem o banco isolado frequenciafacil_test.');
        }
        $this->baseUrl = rtrim($url, '/');
        $this->db = Database::getConnection();
    }

    protected function tearDown(): void
    {
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
            'header' => "Content-Type: $tipo\r\n",
            'content' => $raw ?? ($dados === null ? '' : json_encode((object) $dados, JSON_THROW_ON_ERROR)),
            'ignore_errors' => true,
            'timeout' => 10,
        ]]);
        $body = file_get_contents($this->baseUrl . $path, false, $context);
        self::assertNotFalse($body);
        $headers = $http_response_header;
        preg_match('/^HTTP\/\S+ (\d+)/', $headers[0], $matches);
        self::assertStringContainsString('application/json', implode('\n', $headers));
        return ['status' => (int) $matches[1], 'json' => json_decode($body, true, 512, JSON_THROW_ON_ERROR), 'headers' => $headers];
    }

    private function aluno(string $nome): int
    {
        $response = $this->request('POST', '/api/alunos', ['nome' => $nome, 'telefone' => '18997110001']);
        if (isset($response['json']['id'])) {
            $this->ids[] = $response['json']['id'];
        }
        self::assertSame(201, $response['status']);
        return $response['json']['id'];
    }

    private function salvar(string $data, array $registros): array
    {
        return $this->request('POST', '/api/presencas', compact('data', 'registros'));
    }

    private function registros(int $id): array
    {
        $stmt = $this->db->prepare('SELECT id, data_aula, presente FROM presencas WHERE aluno_id = ? ORDER BY data_aula');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function testConsultaSemAlunosEDataPadrao(): void
    {
        $response = $this->request('GET', '/api/presencas');
        self::assertSame(200, $response['status']);
        self::assertSame(['data' => PresencaValidator::hoje(), 'alunos' => []], $response['json']);
    }

    public function testConsultaOrdenaAlunosESeparaAusenteDeNaoRegistrado(): void
    {
        $zeca = $this->aluno('Zeca Teste');
        $ana = $this->aluno('Ana Teste');
        $bruno = $this->aluno('Bruno Teste');
        self::assertSame(200, $this->salvar('2000-02-29', [
            ['alunoId' => $zeca, 'presente' => false], ['alunoId' => $ana, 'presente' => true],
        ])['status']);
        $response = $this->request('GET', '/api/presencas?data=2000-02-29');
        self::assertSame(200, $response['status']);
        self::assertSame(['data' => '2000-02-29', 'alunos' => [
            ['alunoId' => $ana, 'nome' => 'Ana Teste', 'presente' => true],
            ['alunoId' => $bruno, 'nome' => 'Bruno Teste', 'presente' => null],
            ['alunoId' => $zeca, 'nome' => 'Zeca Teste', 'presente' => false],
        ]], $response['json']);
        self::assertSame([null, null, null], array_column($this->request('GET', '/api/presencas?data=2000-03-01')['json']['alunos'], 'presente'));
    }

    public function testNovoLancamentoAtualizaSemDuplicarEPreservaOutrasDatas(): void
    {
        $id = $this->aluno('Aluno Atualizar');
        $registro = [['alunoId' => $id, 'presente' => true]];
        $response = $this->salvar('2000-01-01', $registro);
        self::assertSame(200, $response['status']);
        self::assertSame(['mensagem' => 'Presença salva', 'total' => 1], $response['json']);
        $original = $this->registros($id)[0]['id'];
        $registro[0]['presente'] = false;
        self::assertSame(200, $this->salvar('2000-01-01', $registro)['status']);
        self::assertSame(200, $this->salvar('2000-01-01', $registro)['status']);
        $rows = $this->registros($id);
        self::assertCount(1, $rows);
        self::assertSame($original, $rows[0]['id']);
        self::assertSame(0, (int) $rows[0]['presente']);
        $registro[0]['presente'] = true;
        self::assertSame(200, $this->salvar('2000-01-02', $registro)['status']);
        self::assertSame([0, 1], array_map('intval', array_column($this->registros($id), 'presente')));
    }

    public function testLoteParcialPreservaAlunoOmitido(): void
    {
        $ana = $this->aluno('Ana Teste');
        $bruno = $this->aluno('Bruno Teste');
        self::assertSame(200, $this->salvar('2000-01-01', [
            ['alunoId' => $ana, 'presente' => true], ['alunoId' => $bruno, 'presente' => false],
        ])['status']);
        self::assertSame(200, $this->salvar('2000-01-01', [['alunoId' => $ana, 'presente' => false]])['status']);
        self::assertSame([false, false], array_column($this->request('GET', '/api/presencas?data=2000-01-01')['json']['alunos'], 'presente'));
    }

    public function testAlunoInexistenteDesfazInsercoesEAtualizacoesDoLote(): void
    {
        $ana = $this->aluno('Ana Teste');
        $bruno = $this->aluno('Bruno Teste');
        self::assertSame(200, $this->salvar('2000-01-01', [['alunoId' => $ana, 'presente' => false]])['status']);
        $antes = $this->registros($ana);
        $response = $this->salvar('2000-01-01', [
            ['alunoId' => $ana, 'presente' => true],
            ['alunoId' => $bruno, 'presente' => true],
            ['alunoId' => 4294967295, 'presente' => false],
        ]);
        self::assertSame(422, $response['status']);
        self::assertArrayHasKey('registros', $response['json']['erros']);
        self::assertSame($antes, $this->registros($ana));
        self::assertSame([], $this->registros($bruno));
    }

    public function testValidaTodoOLoteAntesDeGravar(): void
    {
        $id = $this->aluno('Aluno Validação');
        foreach ([
            [['alunoId' => $id, 'presente' => true], ['alunoId' => $id, 'presente' => false]],
            [['alunoId' => $id, 'presente' => true], ['alunoId' => 2, 'presente' => 'false']],
        ] as $registros) {
            self::assertSame(422, $this->salvar('2000-01-01', $registros)['status']);
            self::assertSame([], $this->registros($id));
        }
    }

    public function testPresencaGravadaPelaApiImpedeExclusaoDoAluno(): void
    {
        $id = $this->aluno('Aluno Com Histórico');
        self::assertSame(200, $this->salvar('2000-01-01', [['alunoId' => $id, 'presente' => true]])['status']);
        self::assertSame(409, $this->request('DELETE', '/api/alunos/' . $id)['status']);
        self::assertCount(1, $this->registros($id));
    }

    public function testRejeitaDatasInvalidasEmConsultaESalvamento(): void
    {
        foreach (['', '2001-02-29', '9999-01-01', '01/01/2000'] as $data) {
            self::assertSame(422, $this->request('GET', '/api/presencas?data=' . rawurlencode($data))['status']);
            self::assertSame(422, $this->salvar($data, [['alunoId' => 1, 'presente' => true]])['status']);
        }
        self::assertSame(422, $this->request('GET', '/api/presencas?data[]=2000-01-01')['status']);
        foreach ([[], ['data' => '2000-01-01'], ['data' => '2000-01-01', 'registros' => []]] as $dados) {
            self::assertSame(422, $this->request('POST', '/api/presencas', $dados)['status']);
        }
    }

    public function testJsonTiposEMetodosInvalidos(): void
    {
        foreach (['{', '[]', 'null'] as $raw) {
            self::assertSame(400, $this->request('POST', '/api/presencas', raw: $raw)['status']);
        }
        self::assertSame(415, $this->request('POST', '/api/presencas', raw: 'data=2000-01-01', tipo: 'text/plain')['status']);
        $response = $this->request('PUT', '/api/presencas', []);
        self::assertSame(405, $response['status']);
        self::assertStringContainsString('Allow: GET, POST', implode('\n', $response['headers']));
        self::assertSame(404, $this->request('GET', '/api/presencas/1')['status']);
    }
}
