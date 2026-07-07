<?php

namespace App\Services;

class SheetMapper
{
    // Mappe les données traitées vers les 34 colonnes du Sheet
    public function versLigne(array $data): array
    {
        $optionsDescriptions = [
            'A' => 'A : Antistatisme',
            'AN' => 'AN : Protection des malléoles',
            'C' => 'C : Chaussures conductrices',
            'CI' => 'CI : Isolation du semelage (froid)',
            'CR' => 'CR : Résistance à la coupure',
            'E' => 'E : Absorption énergie du talon',
            'ESD' => 'ESD : Décharge électrostatique',
            'FO' => 'FO : Résistance aux hydrocarbures',
            'HI' => 'HI : Isolation du semelage (chaud)',
            'HRO' => 'HRO : Résistance à la chaleur de la semelle',
            'LG' => 'LG : Système de grip talon décroché',
            'M' => 'M : Protection des métatarses',
            'P' => 'P : Résistance à la perforation plaque métal',
            'PL' => 'PL : Résistance perforation composite 4.5mm',
            'PS' => 'PS : Résistance perforation composite 3mm',
            'SC' => 'SC : Résistance abrasion pare-pierres',
            'SR' => 'SR : Résistance aux glissements',
            'WPA' => 'WPA : Résistance eau matériaux tige',
            'WR' => 'WR : Résistance eau chaussure entière',
        ];

        $options = array_map(
            fn($code) => $optionsDescriptions[$code] ?? $code,
            $data['options'] ?? []
        );
        $options = array_pad($options, 7, '');

        return [
            '',                                  // Col 1  : Articles — NE PAS TOUCHER
            '',                                  // Col 2  : Images — NE PAS TOUCHER
            '',   // Col 3 : Descriptif/URL — NE PAS TOUCHER
            '',                                  // Col 4  : Fiche Technique — NE PAS TOUCHER
            'Pieds',                             // Col 5  : Famille
            '',                                  // Col 6  : Catégorie — décision Jobsecu
            '',                                  // Col 7  : Sous-Cat — décision Jobsecu
            '',                                  // Col 8  : Sous-Cat — décision Jobsecu
            '',                                  // Col 9  : Sous-Cat — décision Jobsecu
            '',                                  // Col 10 : Sous-Cat — décision Jobsecu
            $data['fournisseur'] ?? '',          // Col 11 : Fournisseurs
            $data['genre_femme'] ?? '',          // Col 12 : Genre Femme
            $data['genre_homme'] ?? '',          // Col 13 : Genre Homme
            $data['genre_mixte'] ?? '',          // Col 14 : Genre Mixte
            $data['type'] ?? '',                 // Col 15 : Type
            $data['fermeture'] ?? '',            // Col 16 : Fermeture
            $data['coquille'] ?? '',             // Col 17 : Coquille
            $data['semelle_anti_perf'] ?? '',    // Col 18 : Semelle
            $data['coloris'] ?? '',              // Col 19 : Coloris
            $data['norme'] ?? '',                // Col 20 : Marquage Normatif
            $options[0],                         // Col 21 : Option 1
            $options[1],                         // Col 22 : Option 2
            $options[2],                         // Col 23 : Option 3
            $options[3],                         // Col 24 : Option 4
            $options[4],                         // Col 25 : Option 5
            $options[5],                         // Col 26 : Option 6
            $options[6],                         // Col 27 : Option 7
            $data['loi_agec'] ?? 'Non',          // Col 28 : Loi AGEC
            '',                                  // Col 29 : Métiers — décision Jobsecu
            '',                                  // Col 30 : Métiers — décision Jobsecu
            '',                                  // Col 31 : Métiers — décision Jobsecu
            '',                                  // Col 32 : Métiers — décision Jobsecu
            '',                                  // Col 33 : Métiers — décision Jobsecu
            '',                                  // Col 34 : Métiers — décision Jobsecu
            $data['nom_woocommerce'] ?? '',  // Col 35 : Nom WooCommerce
            $data['description'] ?? '',      // Col 36 : Description HTML
            implode(' | ', $data['alertes'] ?? []),  // Col 37 : Alertes
            $data['pointures'] ?? '',  // Col AL : Pointures
            $data['poids'] ?? '',            // AM (index 38)
        ];
    }
}