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
        $claude = new \App\Services\ClaudeService();
        $data = $claude->analyserFiche($texte);

        if (!$data) {
            $this->error('❌ Impossible de parser la réponse Claude');
            return;
        }
        $this->info('✅ JSON valide');

        $prompt = <<<PROMPT
Tu es un expert EPI (Équipements de Protection Individuelle).
Analyse cette fiche technique et retourne UNIQUEMENT un objet JSON valide, sans texte avant ou après.

RÈGLES STRICTES :
- Ignore complètement le nom du fichier PDF
- Extrait le nom du modèle UNIQUEMENT depuis le contenu de la fiche technique
- "nom_modele" = nom commercial court (ex: BREEZE, ALICE, X-CLAW PROOF)
- "nom_woocommerce" : format exact "NOM_MODELE // NORME // FOURNISSEUR"
  ex: "BREEZE // S1PL SC SR HRO // S24"

- "description" : texte commercial HTML de 80-100 mots mettant en 
  valeur les points forts du produit, les normes traduites en bénéfices
  clients, et les métiers recommandés. Utilise <p> et <strong>.
  où NORMES = toutes les normes principales séparées par des espaces
- "sku" : la référence fournisseur (ex: 6402, 9XCWH70)
- "nom_court" : le code/référence utilisé dans le tableau SKU de la fiche
  (ex: "AFORHC" depuis "AFORHC38, AFORHC39...", 
       "BREEZE" depuis "RÉF. 6402",
       "ATHORHB" depuis "ATHORHB39, ATHORHB40...")
  C'est le préfixe commun à toutes les références SKU du tableau.
  Si pas de tableau SKU → utilise le nom court du modèle en majuscules.
- "type" doit être exactement : Haute, Basse, Mocassin ou Sabot
  Règles strictes :
  * Haute = chaussure montante au-dessus de la cheville (ranger, botte courte)
  * Basse = chaussure dont la tige s'arrête sous la cheville
  * Mocassin = chaussure sans lacets à enfiler (sabot, mule, sandale)
  * Sabot = sabot rigide type cuisine/médical
  * Si la fiche dit "chaussure basse" ou "low" → Basse
  * Si la fiche dit "chaussure haute", "montante", "high", "ranger" → Haute
  * Si la fiche dit "basket" → Basse
  * En cas de doute → null (ne devine pas)
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
  "nom_court": null,
  "nom_modele": null,
  "nom_woocommerce": "BREEZE // S1PL SC SR HRO // S24",
"description": "<p>La <strong>BREEZE</strong> est...</p>"
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
        // Écriture dans Google Sheets
        $this->info('Écriture dans Google Sheets...');
        $mapper = new \App\Services\SheetMapper();
        $ligne = $mapper->versLigne($resultat);
        $sheets = new \App\Services\GoogleSheetsService();
        // Chercher la ligne par nom du modèle
        // Essayer d'abord le nom du modèle, puis le SKU/référence
        $nomModele = strtoupper(trim($data['nom_court'] ?? $data['nom_modele'] ?? ''));
        $skuRef = strtoupper(trim($data['sku'] ?? ''));
        $this->info('Recherche de "' . $nomModele . '" dans le Sheet...');
        $this->info('Recherche de "' . $nomModele . '" dans le Sheet...');

        // Chercher d'abord par nom du modèle, puis par référence
        $numeroLigne = $sheets->trouverLigneParSku('Produits Pieds', $nomModele);
        if (!$numeroLigne && $skuRef) {
            $this->info('Recherche par référence "' . $skuRef . '"...');
            $numeroLigne = $sheets->trouverLigneParSku('Produits Pieds', $skuRef);
        }

        if ($numeroLigne) {
            $this->info('✅ Produit trouvé ligne ' . $numeroLigne . ' — mise à jour...');
            $succes = $sheets->mettreAJourLigne('Produits Pieds', $numeroLigne, $ligne, $resultat['alertes'] ?? []);
        } else {
            $this->warn('⚠️ Produit non trouvé — ajout en nouvelle ligne...');
            $succes = $sheets->ajouterLigne('Produits Pieds', $ligne);
        }

        if ($succes) {
            $this->info('✅ Ligne ajoutée dans Google Sheets !');
        } else {
            $this->error('❌ Erreur écriture Google Sheets');
        }
    }

}