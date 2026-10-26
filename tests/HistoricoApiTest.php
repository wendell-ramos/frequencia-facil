<?php

declare(strict_types=1);

use App\Config\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AuthenticatedApiClient.php';

final class HistoricoApiTest extends TestCase
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

    private function request(string $method, string $path, ?array $dados = null): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\n" . $this->cabecalhosAutenticacao(),
            'content' => $dados === null ? '' : json_encode((object) $dados, JSON_THROW_ON_ERROR),
            'ignore_errors' => true,
            'timeout' => 10,
        ]]);
        $body = file_get_contents($this->baseUrl . $path, false, $context);
        self::assertNotFalse($body);
        $headers = $http_response_header;
        preg_match('/^HTTP\/\S+ (\d+)/', $headers[0], $matches);
        if ($body !== '') {
            self::assertStringContainsString('application/json', implode('\n', $headers));
        }
        return ['status' => (int) $matches[1], 'json' => $body === '' ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR), 'headers' => $headers];
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

    private function chamada(string $data, array $registros): void
    {
        self::assertSame(200, $this->request('POST', '/api/presencas', compact('data', 'registros'))['status']);
    }

    private function prepararHistorico(): array
    {
        $ana = $this->aluno('Ana Histórico');
        $bruno = $this->aluno('Bruno Histórico');
        $this->chamada('2000-01-01', [['alunoId' => $ana, 'presente' => true]]);
        $this->chamada('2000-01-02', [['alunoId' => $ana, 'presente' => false], ['alunoId' => $bruno, 'presente' => true]]);
        $this->chamada('2000-01-03', [['alunoId' => $ana, 'presente' => true]]);
        $this->chamada('2000-01-04', [['alunoId' => $bruno, 'presente' => false]]);
        return [$ana, $bruno];
    }

    public function testAlunoSemHistoricoRetornaListaVaziaETotaisZerados(): void
    {
        $id = $this->aluno('Aluno Sem Histórico');
        $response = $this->request('GET', "/api/alunos/$id/presencas");
        self::assertSame(200, $response['status']);
        self::assertSame([
            'aluno' => ['id' => $id, 'nome' => 'Aluno Sem Histórico'],
            'totais' => ['presencas' => 0, 'faltas' => 0, 'total' => 0],
            'registros' => [],
        ], $response['json']);
    }

    public function testRetornaTiposOrdemETotaisSomenteDoAlunoEscolhido(): void
    {
        [$ana, $bruno] = $this->prepararHistorico();
        $response = $this->request('GET', "/api/alunos/$ana/presencas?inicio=&fim=");
        self::assertSame(200, $response['status']);
        self::assertSame([
            'aluno' => ['id' => $ana, 'nome' => 'Ana Histórico'],
            'totais' => ['presencas' => 2, 'faltas' => 1, 'total' => 3],
            'registros' => [
                ['dataAula' => '2000-01-03', 'presente' => true],
                ['dataAula' => '2000-01-02', 'presente' => false],
                ['dataAula' => '2000-01-01', 'presente' => true],
            ],
        ], $response['json']);
        // A data 04 só tem chamada de Bruno: não se transforma em falta de Ana.
        $outro = $this->request('GET', "/api/alunos/$bruno/presencas");
        self::assertSame(['presencas' => 1, 'faltas' => 1, 'total' => 2], $outro['json']['totais']);
        self::assertSame(['2000-01-04', '2000-01-02'], array_column($outro['json']['registros'], 'dataAula'));
    }

    #[DataProvider('filtros')]
    public function testFiltraComLimitesInclusivosERecalculaTotais(string $query, array $datas, array $totais): void
    {
        [$id] = $this->prepararHistorico();
        $response = $this->request('GET', "/api/alunos/$id/presencas$query");
        self::assertSame(200, $response['status']);
        self::assertSame($datas, array_column($response['json']['registros'], 'dataAula'));
        self::assertSame($totais, $response['json']['totais']);
    }

    public static function filtros(): array
    {
        return [
            'somente início' => ['?inicio=2000-01-02', ['2000-01-03', '2000-01-02'], ['presencas' => 1, 'faltas' => 1, 'total' => 2]],
            'somente fim' => ['?fim=2000-01-02', ['2000-01-02', '2000-01-01'], ['presencas' => 1, 'faltas' => 1, 'total' => 2]],
            'dois limites' => ['?inicio=2000-01-01&fim=2000-01-02', ['2000-01-02', '2000-01-01'], ['presencas' => 1, 'faltas' => 1, 'total' => 2]],
            'um dia' => ['?inicio=2000-01-02&fim=2000-01-02', ['2000-01-02'], ['presencas' => 0, 'faltas' => 1, 'total' => 1]],
            'sem registros no período' => ['?inicio=2000-02-01&fim=2000-02-29', [], ['presencas' => 0, 'faltas' => 0, 'total' => 0]],
            'fim futuro permitido' => ['?fim=9999-12-31', ['2000-01-03', '2000-01-02', '2000-01-01'], ['presencas' => 2, 'faltas' => 1, 'total' => 3]],
        ];
    }

    public function testAtualizacaoDaChamadaRefleteNoHistoricoSemDuplicar(): void
    {
        [$id] = $this->prepararHistorico();
        $this->chamada('2000-01-02', [['alunoId' => $id, 'presente' => true]]);
        $response = $this->request('GET', "/api/alunos/$id/presencas");
        self::assertSame(200, $response['status']);
        self::assertSame(['presencas' => 3, 'faltas' => 0, 'total' => 3], $response['json']['totais']);
        self::assertCount(3, $response['json']['registros']);
    }

    public function testRejeitaParametrosInvalidos(): void
    {
        $id = $this->aluno('Aluno Filtros');
        foreach ([
            ['inicio=2001-02-29', 'inicio'], ['fim=2000-04-31', 'fim'],
            ['inicio[]=2000-01-01', 'inicio'], ['fim[]=2000-01-01', 'fim'],
            ['inicio=2000-01-03&fim=2000-01-01', 'fim'],
            ['inicio=' . rawurlencode("2000-01-01' OR 1=1 --"), 'inicio'],
        ] as [$query, $campo]) {
            $response = $this->request('GET', "/api/alunos/$id/presencas?$query");
            self::assertSame(422, $response['status']);
            self::assertArrayHasKey('mensagem', $response['json']);
            self::assertArrayHasKey($campo, $response['json']['erros']);
        }
    }

    public function testAlunoInexistenteEIdsInvalidosRetornam404(): void
    {
        $id = $this->aluno('Aluno Removido');
        self::assertSame(204, $this->request('DELETE', "/api/alunos/$id")['status']);
        foreach ([$id, 0, 'abc', 4294967296, '99999999999999999999'] as $invalido) {
            self::assertSame(404, $this->request('GET', "/api/alunos/$invalido/presencas")['status']);
        }
    }

    public function testSomenteGetEhPermitidoEChamadasFicamPreservadas(): void
    {
        [$id] = $this->prepararHistorico();
        foreach (['POST', 'PUT', 'DELETE'] as $method) {
            $response = $this->request($method, "/api/alunos/$id/presencas", []);
            self::assertSame(405, $response['status']);
            self::assertStringContainsString('Allow: GET', implode('\n', $response['headers']));
        }
        $response = $this->request('GET', "/api/alunos/$id/presencas");
        self::assertSame(['presencas' => 2, 'faltas' => 1, 'total' => 3], $response['json']['totais']);
        self::assertSame(404, $this->request('GET', "/api/alunos/$id/presencas/extra")['status']);
    }
}
