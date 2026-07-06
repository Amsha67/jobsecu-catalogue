<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoogleSheetsService;

class TestSheets extends Command
{
    protected $signature = 'test:sheets';
    protected $description = 'Teste la connexion Google Sheets';

    public function handle()
    {
        $this->info('Connexion à Google Sheets...');

        try {
            $sheets = new GoogleSheetsService();

            // Lire les en-têtes de l'onglet Produits Pieds
            $this->info('Lecture des en-têtes...');
            $entetes = $sheets->lireEntetes('Produits Pieds');

            if (empty($entetes)) {
                $this->error('❌ Aucun en-tête trouvé');
                return;
            }

            $this->info('✅ Connexion réussie !');
            $this->info('Colonnes trouvées : ' . count($entetes));
            $this->table(
                ['#', 'Colonne'],
                collect($entetes)->map(fn($v, $k) => [$k + 1, $v])->toArray()
            );

        } catch (\Exception $e) {
            $this->error('❌ Erreur : ' . $e->getMessage());
        }
    }
}