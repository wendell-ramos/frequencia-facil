<?php
namespace App\Models;

/** Tabela `presencas` — um registro por aluno por data de aula. */
final class Presenca
{
    public function __construct(
        public ?int $id,
        public int $alunoId,
        public string $dataAula, // AAAA-MM-DD
        public bool $presente,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self((int) $row['id'], (int) $row['aluno_id'], $row['data_aula'], (bool) $row['presente']);
    }

    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'alunoId'  => $this->alunoId,
            'dataAula' => $this->dataAula,
            'presente' => $this->presente,
        ];
    }
}
