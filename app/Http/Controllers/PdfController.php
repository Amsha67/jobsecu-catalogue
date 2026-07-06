<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Http;
use App\Services\RulesEngine;
use App\Services\SheetMapper;
use App\Services\GoogleSheetsService;

class PdfController extends Controller
{
    public function traiter(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:10240',
            'onglet' => 'required|string',
        ]);

        try {
            // 1. Extraction PDF
            $parser = new Parser();
            $pdf = $parser->parseFile($request->file('pdf')->getPathname());
            $texte = $pdf->getText();

            // 2. Appel Claude
            // 2. Appel Claude
            $claude = new \App\Services\ClaudeService();
            $data = $claude->analyserFiche($texte);

            if (!$data) {
                return response()->json(['erreur' => 'Impossible de parser la réponse Claude'], 500);
            }

            if (!$data) {
                return response()->json(['erreur' => 'Impossible de parser la réponse Claude'], 500);
            }

            // 3. Règles métier
            $engine = new RulesEngine();
            $resultat = $engine->appliquer($data);

            // 4. Google Sheets
            $mapper = new SheetMapper();
            $ligne = $mapper->versLigne($resultat);
            $sheets = new GoogleSheetsService();
            $onglet = $request->input('onglet');
            $nomCourt = strtoupper(trim($data['nom_court'] ?? ''));
            $skuRef = strtoupper(trim($data['sku'] ?? ''));
            $numeroLigne = $sheets->trouverLigneParSku($onglet, $nomCourt);

            if (!$numeroLigne && $skuRef) {
                $numeroLigne = $sheets->trouverLigneParSku($onglet, $skuRef);
            }

            if ($numeroLigne) {
                $sheets->mettreAJourLigne($onglet, $numeroLigne, $ligne);
                $statut = 'mis_a_jour';
            } else {
                $sheets->ajouterLigne($onglet, $ligne);
                $statut = 'ajoute';
            }

            return response()->json([
                'succes' => true,
                'statut' => $statut,
                'numero_ligne' => $numeroLigne,
                'produit' => [
                    'nom' => $data['nom_court'] ?? '',
                    'nom_woocommerce' => $data['nom_woocommerce'] ?? '',
                    'fournisseur' => $resultat['fournisseur'] ?? '',
                    'norme' => $resultat['norme'] ?? '',
                    'pointures' => $resultat['pointures'] ?? '',
                    'type' => $resultat['type'] ?? '',
                    'alertes' => $resultat['alertes'] ?? [],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['erreur' => $e->getMessage()], 500);
        }
    }



}