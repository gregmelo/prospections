<?php

namespace App\DTO;

class Dirigeant
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $nom = null,
        public readonly ?string $prenoms = null,
        public readonly ?string $qualite = null,
        public readonly ?string $denomination = null,
        public readonly ?string $siren = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type_dirigeant'] ?? 'inconnu',
            nom: $data['nom'] ?? null,
            prenoms: $data['prenoms'] ?? null,
            qualite: $data['qualite'] ?? null,
            denomination: $data['denomination'] ?? null,
            siren: $data['siren'] ?? null,
        );
    }

    public function getNomComplet(): string
    {
        if ($this->type === 'personne morale') {
            return $this->denomination ?? 'N/A';
        }
        return trim(($this->prenoms ?? '') . ' ' . ($this->nom ?? '')) ?: 'N/A';
    }
}
