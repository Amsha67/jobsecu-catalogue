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
}