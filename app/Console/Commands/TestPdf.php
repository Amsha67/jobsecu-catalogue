<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Http;

class TestPdf extends Command
{
    protected $signature = 'test:pdf {fichier}';
    protected $description = 'Extrait un PDF et interroge Claude';

    public function handle()
    {
        // 1. Extraction du texte PDF
        $chemin = $this->argument('fichier');
        if (!file_exists($chemin)) {
            $this->error('Fichier introuvable : ' . $chemin);
            return;
        }

        $this->info('Extraction du PDF...');
        $parser = new Parser();
        $pdf = $parser->parseFile($chemin);
        $texte = $pdf->getText();
        $this->info('✅ ' . strlen($texte) . ' caractères extraits');

        // 2. Appel API Claude
        $this->info('Envoi à Claude...');

        $prompt = <<<PROMPT
Tu es un expert EPI (Équipements de Protection Individuelle).
Analyse cette fiche technique et retourne UNIQUEMENT un objet JSON valide, sans texte avant ou après.

RÈGLES STRICTES :
- "type" doit être exactement : Haute, Basse, Mocassin ou Sabot
- "fermeture" doit être exactement : Lacets ou Autres
- "coquille" doit être exactement : Acier, Aluminium, Carbone, Carbone/fibre de verre, Composite, Fibres de verre
- "semelle_anti_perf" doit être exactement : Acier, Composite textile, Inox, Micro filaments light
- "norme" : la norme principale (ex: S3S, S1P, O2...)
- "options" : tableau des codes présents parmi : A, AN, C, CI, CR, E, ESD, FO, HI, HRO, LG, M, P, PL, PS, SC, SR, WPA, WR
- "loi_agec" : true si matériaux recyclés mentionnés, false sinon
- Si une donnée est absente ou incertaine : null
- Ne devine jamais

FICHE TECHNIQUE :
{$texte}

Retourne ce JSON :
{
  "sku": null,
  "nom_modele": null,
  "fournisseur": null,
  "pointures_min": null,
  "pointures_max": null,
  "poids": null,
  "type": null,
  "fermeture": null,
  "coquille": null,
  "semelle_anti_perf": null,
  "coloris": null,
  "norme": null,
  "options": [],
  "loi_agec": false,
  "alertes": []
}
  
PROMPT;

        $response = Http::withoutVerifying()->withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-haiku-4-5',
                    'max_tokens' => 1000,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                ]);

        if ($response->failed()) {
            $this->error('Erreur API Claude : ' . $response->body());
            return;
        }

        // 3. Récupération et affichage du JSON
        $contenu = $response->json()['content'][0]['text'] ?? '';
        $this->info('=== RÉPONSE CLAUDE ===');
        $this->line($contenu);

        // 4. Validation JSON
        // Nettoyer les balises markdown si présentes
        $contenu = preg_replace('/```json\s*/i', '', $contenu);
        $contenu = preg_replace('/```\s*/i', '', $contenu);
        $contenu = trim($contenu);

        $data = json_decode($contenu, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->info('✅ JSON valide');
            $this->table(
                ['Champ', 'Valeur'],
                collect($data)->map(fn($v, $k) => [
                    $k,
                    is_array($v) ? implode(', ', $v) : ($v === null ? 'null' : ($v === true ? 'true' : ($v === false ? 'false' : $v)))
                ])->values()->toArray()
            );
        } else {
            $this->error('❌ JSON invalide : ' . json_last_error_msg());
        }
        // Appliquer les règles métier
        $this->info('=== APRÈS RÈGLES MÉTIER ===');
        $engine = new \App\Services\RulesEngine();
        $resultat = $engine->appliquer($data);
        $this->table(
            ['Champ', 'Valeur'],
            collect($resultat)->map(fn($v, $k) => [
                $k,
                is_array($v) ? implode(', ', $v) : ($v === null ? 'null' : $v)
            ])->values()->toArray()
        );
    }

}