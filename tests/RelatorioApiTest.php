<?php

declare(strict_types=1);

use App\Config\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AuthenticatedApiClient.php';

final class RelatorioApiTest extends TestCase
{
    use AuthenticatedApiClient;

    private string $baseUrl;
    private PDO $db;
    private array $ids = [];
    private const ROTA = '/api/relatorios/frequencia';

    protected function setUp(): void
    {
        if (!getenv('FF_TEST_BASE_URL')) {
            self::markTestSkipped('Execute a integração com banco isolado.');
        }
        if (getenv('MYSQL_DATABASE') !== 'frequenciafacil_test') {
            self::fail('Os testes exigem o banco isolado frequenciafacil_test.');
        }
        $this->baseUrl = rtrim(getenv('FF_TEST_BASE_URL'), '/');
        $this->db = Database::getConnection();
        $this->iniciarAutenticacaoDeTeste();
    }

    protected function tearDown(): void
    {
        $this->removerProfessorDeTeste();
        foreach ($this->ids as $id) {
            $this->db->prepare('DELETE FROM presencas WHERE aluno_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM alunos WHERE id = ?')->execute([$id]);
        }
    }

    private function aluno(string $nome, array $presencas = []): int
    {
        $stmt = $this->db->prepare('INSERT INTO alunos (nome, telefone) VALUES (?, ?)');
        $stmt->execute([$nome, bin2hex(random_bytes(8))]);
        $id = (int) $this->db->lastInsertId();
        $this->ids[] = $id;
        $stmt = $this->db->prepare('INSERT INTO presencas (aluno_id, data_aula, presente) VALUES (?, ?, ?)');
        foreach ($presencas as $dia => $presente) {
            $stmt->execute([$id, '2000-01-' . str_pad((string) $dia, 2, '0', STR_PAD_LEFT), (int) $presente]);
        }
        return $id;
    }

    public function testSemAlunosRetornaListaVaziaEPeriodoNormalizado(): void
    {
        $r = $this->authRequest('GET', self::ROTA . '?inicio=&fim=');
        self::assertSame(200, $r['status']);
        self::assertSame([
            'periodo' => ['inicio' => null, 'fim' => null],
            'frequenciaMinima' => 75,
            'alunos' => [],
        ], $r['json']);
    }

    public function testCalculaTodosOsAlunosSemMisturarRegistrosNemCriarFaltas(): void
    {
        $bruno = $this->aluno('Bruno Relatório', [1 => false]);
        $ana = $this->aluno('Ana Relatório', [1 => true, 2 => false, 3 => true, 4 => true]);
        $carla = $this->aluno('Carla Relatório');
        $daniel = $this->aluno('Daniel Relatório', [5 => true]);
        $eva = $this->aluno('Eva Relatório', [1 => true, 2 => true, 3 => false]);
        $r = $this->authRequest('GET', self::ROTA);
        self::assertSame(200, $r['status']);
        self::assertSame([$ana, $bruno, $carla, $daniel, $eva], array_column($r['json']['alunos'], 'alunoId'));
        self::assertSame([
            ['alunoId' => $ana, 'nome' => 'Ana Relatório', 'presencas' => 3, 'faltas' => 1, 'total' => 4, 'percentual' => 75, 'baixaFrequencia' => false],
            ['alunoId' => $bruno, 'nome' => 'Bruno Relatório', 'presencas' => 0, 'faltas' => 1, 'total' => 1, 'percentual' => 0, 'baixaFrequencia' => true],
            ['alunoId' => $carla, 'nome' => 'Carla Relatório', 'presencas' => 0, 'faltas' => 0, 'total' => 0, 'percentual' => null, 'baixaFrequencia' => false],
            ['alunoId' => $daniel, 'nome' => 'Daniel Relatório', 'presencas' => 1, 'faltas' => 0, 'total' => 1, 'percentual' => 100, 'baixaFrequencia' => false],
            ['alunoId' => $eva, 'nome' => 'Eva Relatório', 'presencas' => 2, 'faltas' => 1, 'total' => 3, 'percentual' => 66.67, 'baixaFrequencia' => true],
        ], $r['json']['alunos']);
    }

    #[DataProvider('periodos')]
    public function testFiltraInclusiveMantendoAlunosSemRegistros(string $query, array $periodo, int $presencas, int $total): void
    {
        $id = $this->aluno('Aluno Período', [1 => true, 2 => false, 3 => true]);
        $sem = $this->aluno('Sem Registros');
        $r = $this->authRequest('GET', self::ROTA . $query);
        self::assertSame(200, $r['status']);
        self::assertSame($periodo, $r['json']['periodo']);
        self::assertSame([$id, $sem], array_column($r['json']['alunos'], 'alunoId'));
        $aluno = $r['json']['alunos'][0];
        self::assertSame($presencas, $aluno['presencas']);
        self::assertSame($total, $aluno['total']);
        self::assertSame($total - $presencas, $aluno['faltas']);
        self::assertEquals($total === 0 ? null : round($presencas * 100 / $total, 2), $aluno['percentual']);
        self::assertSame($total > 0 && $presencas / $total < 0.75, $aluno['baixaFrequencia']);
        self::assertNull($r['json']['alunos'][1]['percentual']);
    }

    public static function periodos(): array
    {
        return [
            ['?inicio=2000-01-02&fim=2000-01-03', ['inicio' => '2000-01-02', 'fim' => '2000-01-03'], 1, 2],
            ['?inicio=2000-01-02', ['inicio' => '2000-01-02', 'fim' => null], 1, 2],
            ['?fim=2000-01-02', ['inicio' => null, 'fim' => '2000-01-02'], 1, 2],
            ['?inicio=2000-01-01&fim=2000-01-01', ['inicio' => '2000-01-01', 'fim' => '2000-01-01'], 1, 1],
            ['?inicio=9999-01-01&fim=9999-12-31', ['inicio' => '9999-01-01', 'fim' => '9999-12-31'], 0, 0],
        ];
    }

    public function testAlunosComMesmoNomePermanecemSeparadosEOrdenadosPorId(): void
    {
        $primeiro = $this->aluno('Mesmo Nome', [1 => true]);
        $segundo = $this->aluno('Mesmo Nome', [1 => false]);
        $r = $this->authRequest('GET', self::ROTA);
        self::assertSame([$primeiro, $segundo], array_column($r['json']['alunos'], 'alunoId'));
        self::assertSame([100, 0], array_column($r['json']['alunos'], 'percentual'));
    }

    #[DataProvider('invalidos')]
    public function testRejeitaPeriodoInvalido(string $query, string $campo): void
    {
        $r = $this->authRequest('GET', self::ROTA . $query);
        self::assertSame(422, $r['status']);
        self::assertArrayHasKey($campo, $r['json']['erros']);
    }

    public static function invalidos(): array
    {
        return [
            ['?inicio=2001-02-29', 'inicio'],
            ['?fim=01%2F01%2F2000', 'fim'],
            ['?inicio[]=2000-01-01', 'inicio'],
            ['?inicio=2000-01-03&fim=2000-01-01', 'fim'],
            ['?inicio=2000-01-01%27%20OR%201=1', 'inicio'],
        ];
    }

    public function testExigeLoginInclusiveComFiltroInvalido(): void
    {
        foreach (['GET', 'POST'] as $method) {
            self::assertSame(401, $this->authRequest($method, self::ROTA . '?inicio=invalido', cookie: '')['status']);
        }
    }

    public function testSomenteGetPermitidoENaoAlteraDados(): void
    {
        $id = $this->aluno('Aluno Protegido', [1 => true]);
        foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            $r = $this->authRequest($method, self::ROTA);
            self::assertSame(405, $r['status']);
            self::assertContains('Allow: GET', $r['headers']);
        }
        self::assertSame([$id], array_column($this->authRequest('GET', self::ROTA)['json']['alunos'], 'alunoId'));
    }
}
