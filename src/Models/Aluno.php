<?php
namespace App\Models;

/** Tabela `alunos` (database/schema.sql). */
final class Aluno
{
    public function __construct(
        public ?int $id,
        public string $nome,
        public string $telefone,
        public ?string $dataNascimento = null, // AAAA-MM-DD
        public ?string $criadoEm = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['nome'],
            $row['telefone'],
            $row['data_nascimento'] ?? null,
            $row['criado_em'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'nome'           => $this->nome,
            'telefone'       => $this->telefone,
            'dataNascimento' => $this->dataNascimento,
            'criadoEm'       => $this->criadoEm,
        ];
    }
}
