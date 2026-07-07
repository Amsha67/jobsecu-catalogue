<?php

namespace App\Services;

class CsvExporter
{
    // ── À CONFIGURER quand WooCommerce sera prêt ──────────────
    // Noms exacts des attributs dans WooCommerce
    private array $attributs = [
        'pointure' => 'Pointure',
        'genre' => 'Genre',
        'type' => 'Type',
        'fermeture' => 'Fermeture',
        'coquille' => 'Coquille',
        'semelle' => 'Semelle anti-perforation',
        'coloris' => 'Coloris',
        'norme' => 'Norme',
    ];

    // Slug des catégories WooCommerce — à adapter
    private array $categories = [
        'Chaussures de sécurité EN ISO 20345:2022' => 'chaussures-de-securite',
        'Chaussures de travail (non sécurité) EN ISO 20347:2022' => 'chaussures-de-travail',
        'Bottes, Cuissardes, Waders' => 'bottes',
        'Sur-chaussures, Système antiglisse' => 'sur-chaussures',
        'Acessoires chaussures' => 'accessoires-chaussures',
        "Produits d'entretien et d'hygiène" => 'entretien-hygiene',
    ];
    // ──────────────────────────────────────────────────────────

    public function generer(array $produits): string
    {
        $lignes = [];

        // En-tête CSV WooCommerce natif
        $lignes[] = $this->entete();

        foreach ($produits as $produit) {
            // Ligne parent (variable)
            $lignes[] = $this->ligneParent($produit);

            // Lignes enfants (variations par pointure)
            if (!empty($produit['pointures']) && str_contains($produit['pointures'], '-')) {
                [$min, $max] = explode('-', $produit['pointures']);
                for ($pt = (int) $min; $pt <= (int) $max; $pt++) {
                    $lignes[] = $this->ligneVariation($produit, $pt);
                }
            }
        }

        return implode("\n", array_map(fn($l) => $this->formatLigne($l), $lignes));
    }

    private function entete(): array
    {
        return [
            'Type',
            'SKU',
            'Parent',
            'Nom',
            'Catégories',
            'Description courte',
            'Description',
            'Attribut 1 nom',
            'Attribut 1 valeur(s)',
            'Attribut 1 visible',
            'Attribut 1 global',
            'Attribut 2 nom',
            'Attribut 2 valeur(s)',
            'Attribut 2 visible',
            'Attribut 2 global',
            'Attribut 3 nom',
            'Attribut 3 valeur(s)',
            'Attribut 3 visible',
            'Attribut 3 global',
            'Attribut 4 nom',
            'Attribut 4 valeur(s)',
            'Attribut 4 visible',
            'Attribut 4 global',
            'Attribut 5 nom',
            'Attribut 5 valeur(s)',
            'Attribut 5 visible',
            'Attribut 5 global',
        ];
    }

    private function ligneParent(array $p): array
    {
        $genres = array_filter([
            $p['genre_femme'] ?? '',
            $p['genre_homme'] ?? '',
            $p['genre_mixte'] ?? '',
        ]);

        $pointures = '';
        if (!empty($p['pointures']) && str_contains($p['pointures'], '-')) {
            [$min, $max] = explode('-', $p['pointures']);
            $pointures = implode(', ', range((int) $min, (int) $max));
        }
        $categorie = $this->categories[$p['categorie'] ?? ''] ?? '';
        $sousCategories = implode(', ', array_filter([
            $p['sous_cat_1'] ?? '',
            $p['sous_cat_2'] ?? '',
            $p['sous_cat_3'] ?? '',
            $p['sous_cat_4'] ?? '',
        ]));

        $categorieComplete = $categorie;
        if ($sousCategories) {
            $categorieComplete .= ', ' . $sousCategories;
        }

        return [
            'variable',                           // Type
            $p['sku'] ?? '',                      // SKU
            '',                                   // Parent
            $p['nom_woocommerce'] ?? '',          // Nom
            $categorieComplete,                   // Catégories
            $p['description_courte'] ?? '',       // Description courte
            $p['description'] ?? '',              // Description
            // Attribut 1 : Pointure
            $this->attributs['pointure'],
            $pointures,
            1,
            1,
            // Attribut 2 : Genre
            $this->attributs['genre'],
            implode(', ', $genres),
            1,
            1,
            // Attribut 3 : Type
            $this->attributs['type'],
            $p['type'] ?? '',
            1,
            1,
            // Attribut 4 : Coquille
            $this->attributs['coquille'],
            $p['coquille'] ?? '',
            1,
            1,
            // Attribut 5 : Norme
            $this->attributs['norme'],
            $p['norme'] ?? '',
            1,
            1,
        ];
    }

    private function ligneVariation(array $p, int $pointure): array
    {
        $sku = ($p['sku'] ?? 'SKU') . '-' . $pointure;

        return [
            'variation',                   // Type
            $sku,                          // SKU variation
            $p['sku'] ?? '',               // Parent
            '',                            // Nom
            '',                            // Catégories
            '',                            // Description courte
            '',                            // Description
            // Attribut 1 : Pointure unique
            $this->attributs['pointure'],
            $pointure,
            1,
            1,
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ];
    }

    private function formatLigne(array $ligne): string
    {
        return implode(',', array_map(function ($val) {
            $val = str_replace('"', '""', (string) $val);
            if (str_contains($val, ',') || str_contains($val, '"') || str_contains($val, "\n")) {
                return '"' . $val . '"';
            }
            return $val;
        }, $ligne));
    }
}