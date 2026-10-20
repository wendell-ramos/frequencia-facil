<?php
namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

/** Garante que a base da API funciona — o CI roda isto desde a Sprint 1. */
final class RouterTest extends TestCase
{
    private function router(): Router
    {
        $r = new Router();
        $r->get('/api/alunos/{id}', fn (Request $req) => Response::json(['id' => $req->param('id')]));
        return $r;
    }

    public function test_rota_com_parametro(): void
    {
        $res = $this->router()->dispatch(new Request('GET', '/api/alunos/7'));
        $this->assertSame(200, $res->status);
        $this->assertSame(['id' => '7'], $res->data);
    }

    public function test_rota_inexistente_retorna_404(): void
    {
        $res = $this->router()->dispatch(new Request('GET', '/api/nada'));
        $this->assertSame(404, $res->status);
    }

    public function test_metodo_errado_retorna_405(): void
    {
        $res = $this->router()->dispatch(new Request('DELETE', '/api/alunos/7'));
        $this->assertSame(405, $res->status);
    }
}
