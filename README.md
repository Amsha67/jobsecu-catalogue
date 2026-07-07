# Jobsecu Catalogue — Import EPI automatisé

Application Laravel + Vue 3 permettant d'extraire automatiquement les données techniques des fiches produits PDF (EPI) et de les injecter dans un Google Sheet partagé, puis de générer un CSV d'import WooCommerce.

## Stack technique

- **Backend** : Laravel 11 (PHP 8.3)
- **Frontend** : Vue 3 + Vite + Tailwind CSS
- **IA** : API Claude (Anthropic) — modèle claude-haiku-4-5
- **Stockage catalogue** : Google Sheets API v4
- **Extraction PDF** : smalot/pdfparser
- **Auth Google** : google/auth + firebase/php-jwt

## Fonctionnalités

- Upload de plusieurs PDFs en drag & drop
- Extraction automatique du texte PDF
- Analyse IA via Claude → JSON structuré
- Moteur de règles métier EPI :
  - Déduction du genre depuis les pointures (≤38 = Femme, ≥43 = Homme)
  - Traduction HV → Haute Visibilité
  - Détection Loi AGEC / matériaux recyclés
  - Déduction catégorie depuis la norme (S → sécurité, O → travail)
  - Normalisation des noms fournisseurs
- Recherche automatique du produit dans Google Sheets par SKU
- Écriture intelligente : ne remplace que les cellules vides
- Coloration des cellules (vert = rempli, jaune = manquant, orange = alerte)
- Colonne Alertes automatique
- Génération du nom WooCommerce : `MODELE // NORMES // FOURNISSEUR`
- Génération de la description HTML commerciale
- Export CSV WooCommerce natif (produits simples avec attributs)
- Support multi-familles EPI (Pieds, Tête, Mains, Corps...)

## Prérequis

- PHP 8.1+
- Composer
- Node.js 18+
- npm
- Compte Anthropic (API Claude)
- Compte Google Cloud (API Sheets)

## Installation

### 1. Cloner le projet

```bash
git clone https://github.com/Amsha67/jobsecu-catalogue.git
cd jobsecu-catalogue
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Installer les dépendances JS

```bash
npm install
```

### 4. Configurer l'environnement

```bash
cp .env.example .env
php artisan key:generate
```

Éditer `.env` et renseigner :

```env
ANTHROPIC_API_KEY=sk-ant-xxxxxxxxxxxxx
GOOGLE_SPREADSHEET_ID=votre_id_spreadsheet
SESSION_DRIVER=file
CACHE_DRIVER=file
```

### 5. Configurer le certificat SSL (Windows / MAMP)

Télécharger https://curl.se/ca/cacert.pem et le placer dans `C:\php\cacert.pem`.

Dans `php.ini` :
```ini
curl.cainfo=C:\php\cacert.pem
openssl.cafile=C:\php\cacert.pem
```

### 6. Configurer Google Sheets

1. Créer un projet sur [console.cloud.google.com](https://console.cloud.google.com)
2. Activer l'API **Google Sheets**
3. Créer un **Compte de service** (IAM → Comptes de service)
4. Générer une **clé JSON** et la placer dans `storage/app/google-credentials.json`
5. Partager le Google Sheet avec l'email du compte de service (rôle Éditeur)

### 7. Lancer l'application

Terminal 1 :
```bash
php artisan serve
```

Terminal 2 :
```bash
npm run dev
```

Ouvrir [http://localhost:8000](http://localhost:8000)

## Utilisation

1. Sélectionner l'onglet Google Sheets cible (ex: Produits Pieds)
2. Glisser les PDFs des fiches techniques dans la zone de dépôt
3. Cliquer sur **Lancer l'import**
4. Vérifier les alertes jaunes dans le Google Sheet
5. Jobsecu complète les colonnes sous-catégories et métiers
6. Cliquer sur **Exporter CSV WooCommerce**
7. Importer le CSV dans WordPress (WooCommerce → Produits → Importer)

## Structure du projet

```
app/
├── Console/Commands/
│   ├── TestPdf.php          # Commande test extraction PDF
│   └── TestSheets.php       # Commande test connexion Google Sheets
├── Http/Controllers/
│   ├── PdfController.php    # Endpoint upload et traitement PDF
│   └── ExportController.php # Endpoint export CSV WooCommerce
└── Services/
    ├── ClaudeService.php        # Appel API Claude + prompt EPI
    ├── RulesEngine.php          # Moteur de règles métier
    ├── SheetMapper.php          # Mapping données → colonnes Sheet
    ├── GoogleSheetsService.php  # Lecture/écriture Google Sheets
    └── CsvExporter.php          # Génération CSV WooCommerce

resources/js/
├── App.vue    # Interface Vue 3 (drag & drop, progression, résultats)
└── app.js     # Point d'entrée Vue

routes/web.php # Routes Laravel
```

## Variables d'environnement

| Variable | Description |
|---|---|
| `ANTHROPIC_API_KEY` | Clé API Anthropic (claude-haiku-4-5) |
| `GOOGLE_SPREADSHEET_ID` | ID du Google Sheet (dans l'URL) |
| `SESSION_DRIVER` | Mettre `file` (pas de base de données requise) |
| `CACHE_DRIVER` | Mettre `file` |

## Structure du Google Sheet

Le Sheet doit avoir ces colonnes dans cet ordre exact :

| Col | Nom | Rempli par |
|---|---|---|
| A | Articles (SKU) | Jobsecu |
| B | Images | Jobsecu |
| C | Descriptif (URL) | Jobsecu |
| D | Fiche Technique | Jobsecu |
| E | Famille | Jobsecu |
| F | Catégorie | App (auto) |
| G-J | Sous-Catégories | Jobsecu |
| K | Fournisseurs | App (auto) |
| L | Genre Femme | App (auto) |
| M | Genre Homme | App (auto) |
| N | Genre Mixte | App (auto) |
| O | Type | App (auto) |
| P | Fermeture | App (auto) |
| Q | Coquille de sécurité | App (auto) |
| R | Semelle anti-perforation | App (auto) |
| S | Coloris | App (auto) |
| T | Marquage Normatif | App (auto) |
| U-AA | Options Additionnelles (x7) | App (auto) |
| AB | Loi AGEC | App (auto) |
| AC-AH | Métiers (x6) | Jobsecu |
| AI | Nom WooCommerce | App (auto) |
| AJ | Description | App (auto) |
| AK | Alertes | App (auto) |
| AL | Pointures | App (auto) |
| AM | Poids | App (auto) |

## Ajouter une nouvelle famille EPI

1. Créer l'onglet dans Google Sheets avec les bonnes colonnes
2. Adapter le prompt dans `ClaudeService.php` si nécessaire
3. Créer un `SheetMapper` spécifique si les colonnes sont différentes
4. Ajouter l'onglet dans le `<select>` de `App.vue`

## Coûts API

- **Claude Haiku** : ~$0.001 par fiche PDF
- **190 fiches** : ~$0.20 au total
- **Google Sheets API** : gratuit (quota largement suffisant)

## Auteur

Développé par **Amsha7** dans le cadre d'un projet freelance BTS SIO SLAM.
