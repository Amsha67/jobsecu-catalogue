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

    public function mettreAJourLigne(string $onglet, int $numeroLigne, array $valeurs): bool
    {
        $sheetId = $this->getSheetId($onglet);
        $requests = [];

        // Mapping colonne index → lettre
        $colonnes = [
            5 => 'F',  // Catégorie
            10 => 'K',  // Fournisseurs
            11 => 'L',  // Genre Femme
            12 => 'M',  // Genre Homme
            13 => 'N',  // Genre Mixte
            14 => 'O',  // Type
            15 => 'P',  // Fermeture
            16 => 'Q',  // Coquille
            17 => 'R',  // Semelle
            18 => 'S',  // Coloris
            19 => 'T',  // Norme
            20 => 'U',  // Option 1
            21 => 'V',  // Option 2
            22 => 'W',  // Option 3
            23 => 'X',  // Option 4
            24 => 'Y',  // Option 5
            25 => 'Z',  // Option 6
            26 => 'AA', // Option 7
            27 => 'AB', // Loi AGEC
            34 => 'AI', // Nom WooCommerce
            35 => 'AJ', // Description
            36 => 'AK', // Alertes
        ];

        foreach ($colonnes as $index => $lettre) {
            $valeur = $valeurs[$index] ?? '';

            // Pour la colonne F — écrire uniquement si vide
            if ($lettre === 'F') {
                if (!$this->celluleEstVide($onglet, $numeroLigne, 'F'))
                    continue;
            }

            // Ne pas écraser si Claude n'a rien trouvé
            if ($valeur === '' || $valeur === null)
                continue;

            // Couleur selon présence
            $couleur = ['red' => 0.85, 'green' => 0.95, 'blue' => 0.85]; // vert

            $colNum = $this->lettreVersIndex($lettre);

            // Écriture de la valeur
            $requests[] = [
                'updateCells' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => $numeroLigne - 1,
                        'endRowIndex' => $numeroLigne,
                        'startColumnIndex' => $colNum,
                        'endColumnIndex' => $colNum + 1,
                    ],
                    'rows' => [
                        [
                            'values' => [
                                [
                                    'userEnteredValue' => ['stringValue' => (string) $valeur],
                                    'userEnteredFormat' => [
                                        'backgroundColor' => $couleur,
                                    ],
                                ]
                            ],
                        ]
                    ],
                    'fields' => 'userEnteredValue,userEnteredFormat.backgroundColor',
                ],
            ];
        }

        // Colonnes importantes vides → jaune
        $colonnesObligatoires = [16, 17, 18, 19]; // Coquille, Semelle, Coloris, Norme
        foreach ($colonnesObligatoires as $index) {
            $valeur = $valeurs[$index] ?? '';
            if ($valeur !== '' && $valeur !== null)
                continue;

            $lettre = $colonnes[$index] ?? null;
            if (!$lettre)
                continue;

            $colNum = $this->lettreVersIndex($lettre);
            $requests[] = [
                'updateCells' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => $numeroLigne - 1,
                        'endRowIndex' => $numeroLigne,
                        'startColumnIndex' => $colNum,
                        'endColumnIndex' => $colNum + 1,
                    ],
                    'rows' => [
                        [
                            'values' => [
                                [
                                    'userEnteredFormat' => [
                                        'backgroundColor' => ['red' => 1.0, 'green' => 0.9, 'blue' => 0.0],
                                    ],
                                ]
                            ],
                        ]
                    ],
                    'fields' => 'userEnteredFormat.backgroundColor',
                ],
            ];
        }
        // Alertes → fond orange si présentes
        $alertesValeur = $valeurs[36] ?? '';
        if (!empty($alertesValeur)) {
            $colNum = $this->lettreVersIndex('AK');
            $requests[] = [
                'updateCells' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => $numeroLigne - 1,
                        'endRowIndex' => $numeroLigne,
                        'startColumnIndex' => $colNum,
                        'endColumnIndex' => $colNum + 1,
                    ],
                    'rows' => [
                        [
                            'values' => [
                                [
                                    'userEnteredValue' => ['stringValue' => $alertesValeur],
                                    'userEnteredFormat' => [
                                        'backgroundColor' => ['red' => 1.0, 'green' => 0.7, 'blue' => 0.0],
                                    ],
                                ]
                            ],
                        ]
                    ],
                    'fields' => 'userEnteredValue,userEnteredFormat.backgroundColor',
                ],
            ];
        }

        if (empty($requests))
            return true;

        $urlBatch = "{$this->baseUrl}/{$this->spreadsheetId}:batchUpdate";
        $response = Http::withoutVerifying()
            ->withToken($this->accessToken)
            ->post($urlBatch, ['requests' => $requests]);

        return $response->successful();
    }

    private function lettreVersIndex(string $lettre): int
    {
        $lettre = strtoupper($lettre);
        $result = 0;
        for ($i = 0; $i < strlen($lettre); $i++) {
            $result = $result * 26 + (ord($lettre[$i]) - ord('A') + 1);
        }
        return $result - 1; // 0-based
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
    public function celluleEstVide(string $onglet, int $ligne, string $colonne): bool
    {
        $range = urlencode($onglet . '!' . $colonne . $ligne . ':' . $colonne . $ligne);
        $url = "{$this->baseUrl}/{$this->spreadsheetId}/values/{$range}";
        $response = Http::withoutVerifying()
            ->withToken($this->accessToken)
            ->get($url);
        $valeurs = $response->json()['values'] ?? [];
        return empty($valeurs);
    }
}