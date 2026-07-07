<?php

namespace App\Services;

class RulesEngine
{
    // Dictionnaire fournisseurs — corrige les noms bruts des PDFs
    private array $fournisseurs = [
        'BOSSI INDUSTRIE' => 'S24',
        'BOSSI' => 'S24',
        'COFRA' => 'Cofra',
        'COVERGUARD' => 'Coverguard',
        'SAFETY JOGGER' => 'Safety Jogger',
        'ACTIVE GEAR' => 'Active Gear',
        'ABEBA' => 'Abeba',
        'SINGER' => 'Singer',
        'DELTA PLUS' => 'Delta+',
        'DELTAPLUS' => 'Delta+',
        'PORTWEST' => 'Portwest',
        'DUNLOP' => 'Dunlop',
        'BEKINA' => 'Bekina',
    ];

    // Normes valides dans le Sheet de Jobsecu
    private array $normes_valides = [
        'O1',
        'O2',
        'O3',
        'O3L',
        'O3S',
        'O4',
        'O5',
        'O5L',
        'O5S',
        'O6',
        'O7',
        'O7L',
        'O7S',
        'S1',
        'S1P',
        'S1PL',
        'S1PS',
        'S2',
        'S3',
        'S3L',
        'S3S',
        'S4',
        'S5',
        'S5L',
        'S5S',
        'S6',
        'S7',
        'S7L',
        'S7S',
    ];

    public function appliquer(array $data): array
    {
        return [
            'sku' => $this->nettoyerSku($data['sku']),
            'nom_modele' => $data['nom_modele'],
            'nom_court' => $data['nom_court'] ?? null,
            'nom_woocommerce' => $data['nom_woocommerce'] ?? null,
            'categorie' => $data['categorie'] ?? null,
            'description' => $data['description'] ?? null,
            'fournisseur' => $this->normaliserFournisseur($data['fournisseur']),
            'pointures' => $this->formaterPointures($data['pointures_min'], $data['pointures_max']),
            'poids' => $data['poids'] ? $data['poids'] . 'g' : null,
            'genre_femme' => $this->genreFemme($data['pointures_min']),
            'genre_homme' => $this->genreHomme($data['pointures_max']),
            'genre_mixte' => $this->genreMixte($data['pointures_min'], $data['pointures_max']),
            'type' => $this->deduireType($data['type'], $data['nom_modele']),
            'fermeture' => $data['fermeture'],
            'coquille' => $data['coquille'],
            'semelle_anti_perf' => $data['semelle_anti_perf'],
            'coloris' => $this->traduireColoris($data['coloris']),
            'norme' => $this->validerNorme($data['norme']),
            'options' => $data['options'] ?? [],
            'loi_agec' => $data['loi_agec'] ? 'Oui' : 'Non',
            'alertes' => $this->genererAlertes($data),
        ];
    }

    private function nettoyerSku(?string $sku): ?string
    {
        if (!$sku)
            return null;
        return strtoupper(trim($sku));
    }

    private function normaliserFournisseur(?string $fournisseur): ?string
    {
        if (!$fournisseur)
            return null;
        $upper = strtoupper(trim($fournisseur));
        foreach ($this->fournisseurs as $brut => $propre) {
            if (str_contains($upper, $brut))
                return $propre;
        }
        return $fournisseur;
    }

    private function formaterPointures(?int $min, ?int $max): ?string
    {
        if (!$min || !$max)
            return null;
        return $min . '-' . $max;
    }

    private function genreFemme(?int $min): string
    {
        return ($min !== null && $min <= 38) ? 'Femme' : '';
    }

    private function genreHomme(?int $max): string
    {
        return ($max !== null && $max >= 43) ? 'Homme' : '';
    }

    private function genreMixte(?int $min, ?int $max): string
    {
        return ($min !== null && $max !== null && $min <= 38 && $max >= 43) ? 'Mixte' : '';
    }

    private function traduireColoris(?string $coloris): ?string
    {
        if (!$coloris)
            return null;
        // HV → Haute Visibilité
        $coloris = str_ireplace(
            ['HV jaune', 'HV orange', 'HV rouge', 'HV'],
            ['Haute Visibilité jaune', 'Haute Visibilité orange', 'Haute Visibilité rouge', 'Haute Visibilité'],
            $coloris
        );
        return $coloris;
    }

    private function validerNorme(?string $norme): ?string
    {
        if (!$norme)
            return null;
        if (!in_array($norme, $this->normes_valides)) {
            // Norme non standard — on la garde mais on alertera
            return $norme;
        }
        return $norme;
    }

    private function genererAlertes(array $data): array
    {
        $alertes = $data['alertes'] ?? [];

        if (!$data['sku']) {
            $alertes[] = 'SKU manquant — à remplir manuellement';
        }
        if (!$data['coloris']) {
            $alertes[] = 'Coloris non trouvé dans le PDF';
        }
        if ($data['norme'] && !in_array($data['norme'], $this->normes_valides)) {
            $alertes[] = 'Norme "' . $data['norme'] . '" non standard — à vérifier';
        }
        if (!$data['pointures_min'] || !$data['pointures_max']) {
            $alertes[] = 'Pointures incomplètes — à vérifier';
        }

        return $alertes;
    }

    private function deduireType(?string $type, ?string $nomModele): ?string
    {
        if ($type)
            return $type;
        if (!$nomModele)
            return null;

        $nom = strtoupper($nomModele);

        $motsHaute = ['HIGH', 'HAUTE', 'RANGER', 'MONTANT', 'HI ', 'BOOT'];
        $motsBasse = ['LOW', 'BASSE', 'BASKET', 'BREEZE', 'SPRINT'];

        foreach ($motsHaute as $mot) {
            if (str_contains($nom, $mot))
                return 'Haute';
        }
        foreach ($motsBasse as $mot) {
            if (str_contains($nom, $mot))
                return 'Basse';
        }

        return $type; // Retourne ce que Claude a dit
    }
}