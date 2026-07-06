<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;

class GoogleSheetsService
{
    private string $spreadsheetId;
    private string $accessToken;
    private string $baseUrl = 'https://sheets.googleapis.com/v4/spreadsheets';

    public function __construct()
    {
        $this->spreadsheetId = config('services.google.spreadsheet_id');
        $this->accessToken = $this->getAccessToken();
    }

    private function getAccessToken(): string
    {
        $credentialsPath = storage_path('app/google-credentials.json');
        $credentials = json_decode(file_get_contents($credentialsPath), true);

        // Créer le JWT manuellement
        $now = time();
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));

        $claim = base64_encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $toSign = $header . '.' . $claim;
        $signature = '';
        openssl_sign($toSign, $signature, $credentials['private_key'], 'SHA256');
        $jwt = $toSign . '.' . base64_encode($signature);

        // Appel token sans vérification SSL
        $response = Http::withoutVerifying()
            ->asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if ($response->failed()) {
            throw new \Exception('Erreur token Google : ' . $response->body());
        }

        return $response->json()['access_token'];
    }

    public function ajouterLigne(string $onglet, array $valeurs): bool
    {
        $range = urlencode($onglet . '!A:A');
        $url = "{$this->baseUrl}/{$this->spreadsheetId}/values/{$range}:append";

        $response = Http::withoutVerifying()
            ->withToken($this->accessToken)
            ->withQueryParameters([
                'valueInputOption' => 'USER_ENTERED',
                'insertDataOption' => 'INSERT_ROWS',
            ])
            ->post($url, [
                'values' => [$valeurs],
            ]);

        return $response->successful();
    }

    public function lireLigne(string $onglet, int $ligne): array
    {
        $range = urlencode($onglet . '!' . $ligne . ':' . $ligne);
        $url = "{$this->baseUrl}/{$this->spreadsheetId}/values/{$range}";

        $response = Http::withoutVerifying()
            ->withToken($this->accessToken)
            ->get($url);

        return $response->json()['values'][0] ?? [];
    }

    public function lireEntetes(string $onglet): array
    {
        $range = urlencode($onglet . '!1:1');
        $url = "{$this->baseUrl}/{$this->spreadsheetId}/values/{$range}";

        $response = Http::withoutVerifying()
            ->withToken($this->accessToken)
            ->get($url);

        return $response->json()['values'][0] ?? [];
    }

    public function trouverLigneParSku(string $onglet, string $sku): ?int
    {
        $range = urlencode($onglet . '!A:A');
        $url = "{$this->baseUrl}/{$this->spreadsheetId}/values/{$range}";

        $response = Http::withoutVerifying()
            ->withToken($this->accessToken)
            ->get($url);

        $valeurs = $response->json()['values'] ?? [];

        foreach ($valeurs as $index => $ligne) {
            $valeur = strtoupper(trim($ligne[0] ?? ''));
            $search = strtoupper(trim($sku));

            // Match exact ou partiel (ex: "X-CLAW PROOF/9XCWH70" contient "9XCWH70")
            if ($valeur === $search || str_contains($valeur, $search)) {
                return $index + 1; // +1 car Google Sheets commence à 1
            }
        }

        return null; // Non trouvé
    }

    public function mettreAJourLigne(string $onglet, int $numeroLigne, array $valeurs, array $alertes = []): bool
    {
        // Écriture des données depuis colonne C
        $valeursDepuisC = array_slice($valeurs, 2);
        $range = urlencode($onglet . '!C' . $numeroLigne . ':AJ' . $numeroLigne);
        $url = "{$this->baseUrl}/{$this->spreadsheetId}/values/{$range}";

        $response = Http::withoutVerifying()
            ->withToken($this->accessToken)
            ->withQueryParameters(['valueInputOption' => 'USER_ENTERED'])
            ->put($url, ['values' => [$valeursDepuisC]]);

        if (!$response->successful())
            return false;

        // Colorier les cellules selon leur statut
        $requests = [];

        // Colonnes à vérifier (index 0 = col A)
        $colonnesImportantes = [
            10 => 'Fournisseurs',      // K
            11 => 'Genre Femme',       // L
            14 => 'Type',              // O
            15 => 'Fermeture',         // P
            16 => 'Coquille',          // Q
            17 => 'Semelle',           // R
            18 => 'Coloris',           // S
            19 => 'Norme',             // T
        ];

        foreach ($colonnesImportantes as $colIndex => $nom) {
            $valeur = $valeurs[$colIndex] ?? '';

            if (empty($valeur)) {
                // Jaune = donnée manquante
                $couleur = ['red' => 1.0, 'green' => 0.9, 'blue' => 0.0];
            } else {
                // Vert clair = donnée présente
                $couleur = ['red' => 0.85, 'green' => 0.95, 'blue' => 0.85];
            }

            $requests[] = [
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $this->getSheetId($onglet),
                        'startRowIndex' => $numeroLigne - 1,
                        'endRowIndex' => $numeroLigne,
                        'startColumnIndex' => $colIndex,
                        'endColumnIndex' => $colIndex + 1,
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'backgroundColor' => $couleur,
                        ],
                    ],
                    'fields' => 'userEnteredFormat.backgroundColor',
                ],
            ];
        }

        // Envoyer les couleurs
        if (!empty($requests)) {
            $urlBatch = "{$this->baseUrl}/{$this->spreadsheetId}:batchUpdate";
            Http::withoutVerifying()
                ->withToken($this->accessToken)
                ->post($urlBatch, ['requests' => $requests]);
        }

        return true;
    }

    private function getSheetId(string $onglet): int
    {
        $url = "{$this->baseUrl}/{$this->spreadsheetId}";
        $response = Http::withoutVerifying()
            ->withToken($this->accessToken)
            ->get($url);

        $sheets = $response->json()['sheets'] ?? [];
        foreach ($sheets as $sheet) {
            if ($sheet['properties']['title'] === $onglet) {
                return $sheet['properties']['sheetId'];
            }
        }
        return 0;
    }
}