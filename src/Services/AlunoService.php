<?php
namespace App\Services;

use App\Repositories\AlunoRepository;

/**
 * Regras de negócio da US01:
 *  - nome e telefone obrigatórios
 *  - não pode haver dois alunos com o mesmo nome + telefone
 *  - dados inválidos => mensagem de erro clara
 * Mantenha as validações em métodos puros para serem fáceis de testar (tests/Unit).
 */
final class AlunoService
{
    public function __construct(private ?AlunoRepository $repo = null) {}

    private function repo(): AlunoRepository
    {
        return $this->repo ??= new AlunoRepository();
    }

    /**
     * Retorna lista de erros por campo. Vazio = válido.
     * @return array<string, string>
     */
    public function validar(array $dados): array
    {
        // TODO(Wendell)
        return [];
    }
}
