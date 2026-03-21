<?php

namespace App\DTO;

class Company
{
    /**
     * @param Dirigeant[] $dirigeants
     * @param array<string, mixed> $siege
     */
    public function __construct(
        public readonly string $siren,
        public readonly string $nomComplet,
        public readonly ?string $sigle,
        public readonly ?string $dateCreation,
        public readonly ?string $activitePrincipale,
        public readonly ?string $categorieEntreprise,
        public readonly ?string $natureJuridique,
        public readonly ?string $trancheEffectifSalarie,
        public readonly array $siege,
        public readonly array $dirigeants,
        public readonly ?string $etatAdministratif,
        public readonly array $matchingEtablissements = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $dirigeants = array_map(
            fn(array $d) => Dirigeant::fromArray($d),
            $data['dirigeants'] ?? []
        );

        return new self(
            siren: $data['siren'] ?? '',
            nomComplet: $data['nom_complet'] ?? $data['nom_raison_sociale'] ?? '',
            sigle: $data['sigle'] ?? null,
            dateCreation: $data['date_creation'] ?? null,
            activitePrincipale: $data['activite_principale'] ?? null,
            categorieEntreprise: $data['categorie_entreprise'] ?? null,
            natureJuridique: $data['nature_juridique'] ?? null,
            trancheEffectifSalarie: $data['tranche_effectif_salarie'] ?? null,
            siege: $data['siege'] ?? [],
            dirigeants: $dirigeants,
            etatAdministratif: $data['etat_administratif'] ?? null,
            matchingEtablissements: $data['matching_etablissements'] ?? [],
        );
    }

    public function getAdresseSiege(): string
    {
        return $this->siege['adresse'] ?? 'Adresse non disponible';
    }

    public function getCodePostalSiege(): string
    {
        return $this->siege['code_postal'] ?? '';
    }

    public function getVilleSiege(): string
    {
        return $this->siege['libelle_commune'] ?? '';
    }

    public function getFirstMatchingEtablissementAddress(): ?string
    {
        if (empty($this->matchingEtablissements)) {
            return null;
        }

        $etab = $this->matchingEtablissements[0];
        
        // If it's the exact same as the siege, no need to return it
        $etabCodePostal = $etab['code_postal'] ?? '';
        $siegeCodePostal = $this->getCodePostalSiege();
        
        if ($etabCodePostal === $siegeCodePostal) {
            return null;
        }
        
        return $etab['adresse'] ?? null;
    }

    public function getAnneeCreation(): ?int
    {
        if (!$this->dateCreation) {
            return null;
        }
        return (int) substr($this->dateCreation, 0, 4);
    }

    public function getLibelleActivitePrincipale(): string
    {
        return \App\Service\NafService::getLibelleFromCode($this->activitePrincipale);
    }

    public function getLibelleEffectif(): string
    {
        return match ($this->trancheEffectifSalarie) {
            '00' => 'Aucun salarié',
            '01' => '1 ou 2 salariés',
            '02' => '3 à 5 salariés',
            '03' => '6 à 9 salariés',
            '11' => '10 à 19 salariés',
            '12' => '20 à 49 salariés',
            '21' => '50 à 99 salariés',
            '22' => '100 à 199 salariés',
            '31' => '200 à 249 salariés',
            '32' => '250 à 499 salariés',
            '41' => '500 à 999 salariés',
            '42' => '1 000 à 1 999 salariés',
            '51' => '2 000 à 4 999 salariés',
            '52' => '5 000 à 9 999 salariés',
            '53' => '10 000 salariés ou plus',
            'NN', null => 'Non renseigné',
            default => 'Non renseigné',
        };
    }

    public function getLibelleNatureJuridique(): string
    {
        return match ($this->natureJuridique) {
            '1000' => 'Entrepreneur individuel',
            '5499' => 'Société à responsabilité limitée (SARL)',
            '5710' => 'Société par actions simplifiée (SAS)',
            '5720' => 'Société par actions simplifiée unipersonnelle (SASU)',
            '5498' => 'EURL',
            '5599' => 'SA',
            '6540' => 'Association loi 1901',
            '5308' => 'EIRL',
            '1110' => 'Artisan',
            '5310' => 'SARL unipersonnelle',
            default => $this->natureJuridique ?? 'N/A',
        };
    }

    public function isActive(): bool
    {
        return $this->etatAdministratif === 'A';
    }

    public function getDirigeantsPhysiques(): array
    {
        return array_filter(
            $this->dirigeants,
            fn(Dirigeant $d) => $d->type === 'personne physique'
        );
    }
}
