# Documentation Technique — Jobsecu Catalogue

## Architecture générale

```
[Navigateur Vue 3]
      │
      │ POST /api/traiter-pdf (FormData)
      ▼
[PdfController]
      │
      ├─ smalot/pdfparser → texte brut
      │
      ├─ ClaudeService → JSON structuré
      │     └─ Prompt strict avec dictionnaire EPI
      │
      ├─ RulesEngine → données normalisées
      │     ├─ Genre depuis pointures
      │     ├─ HV → Haute Visibilité
      │     ├─ Catégorie depuis norme
      │     └─ Normalisation fournisseurs
      │
      ├─ SheetMapper → tableau 38 colonnes
      │
      └─ GoogleSheetsService
            ├─ trouverLigneParSku() → numéro de ligne
            └─ mettreAJourLigne() → écriture + coloration
```

## ClaudeService — Le prompt

Le prompt est la pièce centrale du système. Il définit :

### Champs extraits

```json
{
  "sku": "référence fournisseur ou préfixe SKU",
  "nom_court": "nom commercial court (pour matcher le Sheet)",
  "nom_modele": "nom complet du modèle",
  "nom_woocommerce": "NOM // NORMES // FOURNISSEUR",
  "description": "HTML commercial 80-100 mots",
  "categorie": "catégorie exacte WooCommerce",
  "fournisseur": "nom du fournisseur",
  "pointures_min": 36,
  "pointures_max": 48,
  "poids": 577,
  "type": "Haute|Basse|Mocassin|Sabot",
  "fermeture": "Lacets|Autres",
  "coquille": "Acier|Aluminium|Carbone|...",
  "semelle_anti_perf": "Acier|Composite textile|...",
  "coloris": "couleur principale",
  "norme": "S3S|S1P|O2|...",
  "options": ["CI", "ESD", "FO", "SR"],
  "loi_agec": false,
  "alertes": []
}
```

### Règles strictes du prompt

- `nom_court` = préfixe commun des SKUs dans le tableau (ex: AFORHC depuis AFORHC38, AFORHC39...)
- `type` = déduit depuis description physique (au-dessus cheville = Haute)
- `coquille` = valeur exacte parmi le dictionnaire (jamais d'invention)
- Si donnée absente → `null` (jamais de devinette)
- Ignorer le nom du fichier PDF

## RulesEngine — Les règles métier

### Règle genre (pointures)
```php
≤ 38 → Genre Femme
≥ 43 → Genre Homme  
Les deux → Femme + Homme + Mixte
```

### Règle HV (coloris)
```php
"HV jaune" → "Haute Visibilité jaune"
"HV orange" → "Haute Visibilité orange"
"HV" → "Haute Visibilité"
```

### Règle catégorie (norme)
```php
Commence par "S" → Chaussures de sécurité EN ISO 20345:2022
Commence par "O" → Chaussures de travail EN ISO 20347:2022
Autre → null (Jobsecu décide)
```

### Normalisation fournisseurs
```php
"BOSSI INDUSTRIE" → "S24"
"BOSSI"           → "S24"
"COFRA"           → "Cofra"
// etc.
```

### Alertes automatiques
- SKU manquant
- Coloris non trouvé
- Norme non standard
- Pointures incomplètes

## GoogleSheetsService — Authentification

On n'utilise pas le SDK Google complet (trop lourd).
On génère manuellement un JWT signé avec la clé privée RSA du compte de service.

```
1. Lire google-credentials.json
2. Construire le payload JWT :
   - iss: client_email
   - scope: sheets.googleapis.com
   - aud: oauth2.googleapis.com/token
   - exp: maintenant + 3600s
3. Signer avec openssl_sign (RSA-SHA256)
4. POST /token → access_token
5. Utiliser access_token dans les requêtes Sheets
```

Le token dure 1h. Pour une app en production, il faudrait implémenter un cache du token.

## GoogleSheetsService — Écriture intelligente

On utilise `batchUpdate` (pas `values.update`) pour écrire cellule par cellule.

**Pourquoi ?**
- `values.update` écrit toute une plage → écrase les cellules vides
- `batchUpdate` avec `updateCells` → contrôle total cellule par cellule

**Logique d'écriture :**
```
Pour chaque colonne à écrire :
  Si valeur vide ou null → SKIP (ne pas écrire)
  Si valeur présente → écrire + colorer en vert
  
Pour colonnes importantes vides (Coquille, Semelle, Coloris, Norme) :
  → colorer en jaune (alerte visuelle pour Jobsecu)
  
Pour colonne Alertes non vide :
  → colorer en orange
  
Pour colonne Catégorie (F) :
  → écrire uniquement si la cellule est vide
  → ne jamais écraser ce que Jobsecu a mis
```

## Mapping colonnes Sheet ↔ Index

Les colonnes du Sheet sont référencées par leur index 0-based dans le tableau PHP.

```
Index 0  = Col A  = Articles (SKU)
Index 4  = Col E  = Famille
Index 5  = Col F  = Catégorie
Index 10 = Col K  = Fournisseurs
Index 11 = Col L  = Genre Femme
Index 12 = Col M  = Genre Homme
Index 13 = Col N  = Genre Mixte
Index 14 = Col O  = Type
Index 15 = Col P  = Fermeture
Index 16 = Col Q  = Coquille
Index 17 = Col R  = Semelle
Index 18 = Col S  = Coloris
Index 19 = Col T  = Norme
Index 20-26 = U-AA = Options (x7)
Index 27 = Col AB = Loi AGEC
Index 34 = Col AI = Nom WooCommerce
Index 35 = Col AJ = Description
Index 36 = Col AK = Alertes
Index 37 = Col AL = Pointures
Index 38 = Col AM = Poids
```

## CsvExporter — Format WooCommerce

Le CSV suit le format natif WooCommerce (sans plugin).

**Colonnes générées :**
```
Type, SKU, Parent, Nom, Catégories, Description courte, Description,
Attribut 1 nom, Attribut 1 valeur(s), Attribut 1 visible, Attribut 1 global,
Attribut 2 nom, Attribut 2 valeur(s), ...
```

**Attributs exportés :**
1. Pointure (valeurs séparées par virgule : 36, 37, 38...)
2. Genre (Femme, Homme, Mixte)
3. Type (Haute, Basse...)
4. Coquille
5. Norme

**Catégories :**
Le slug WooCommerce est déduit depuis le nom de catégorie :
```
"Chaussures de sécurité EN ISO 20345:2022" → "chaussures-de-securite"
```
⚠️ À adapter selon les slugs réels créés dans WordPress.

## Ajouter une nouvelle famille EPI

### Étape 1 — Analyser le Sheet
Lire les colonnes exactes de l'onglet (ex: "Produits Tête")

### Étape 2 — Adapter le prompt Claude
Dans `ClaudeService.php`, le prompt peut rester générique si les champs sont similaires. Sinon créer `app/Services/Families/TeteFamily.php` avec son propre prompt.

### Étape 3 — Adapter SheetMapper
Si les colonnes sont différentes, créer `app/Services/SheetMappers/TeteMapper.php` avec le mapping spécifique.

### Étape 4 — Adapter GoogleSheetsService
Le tableau `$colonnes` dans `mettreAJourLigne()` doit refléter les colonnes de la nouvelle famille.

### Étape 5 — Ajouter dans l'interface
Dans `App.vue`, ajouter l'option dans le `<select>` :
```vue
<option value="Produits Têtes">Produits Têtes</option>
```

## Problèmes connus et solutions

### SSL Certificate Error (Windows/MAMP)
```
cURL error 60: SSL certificate problem
```
**Solution :** Télécharger cacert.pem et configurer php.ini
OU utiliser `Http::withoutVerifying()` en développement local uniquement.

### JSON invalide retourné par Claude
Claude peut entourer le JSON de balises markdown (```json```).
**Solution :** Regex de nettoyage avant `json_decode()`.

### Encodage UTF-8 (certains PDFs)
Certains PDFs ont des caractères mal encodés.
**Solution :**
```php
$texte = mb_convert_encoding($texte, 'UTF-8', 'UTF-8');
$texte = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texte);
```

### SKU non trouvé dans le Sheet
Claude extrait un nom différent de celui dans le Sheet.
**Solution :** Le système essaie d'abord `nom_court` puis `sku` (référence).
Si toujours introuvable → ligne ajoutée en bas (à déplacer manuellement).

### Google Sheets — fichier Excel non supporté
```
This operation is not supported for this document. The document must not be an Office file.
```
**Solution :** Convertir le fichier Excel en Google Sheets natif via
Fichier → Enregistrer en tant que Google Sheets.

## Déploiement en production

Pour déployer sur un serveur et rendre l'app accessible à Jobsecu :

1. Hébergement PHP recommandé : o2switch, PlanetHoster, ou VPS
2. Copier le projet sur le serveur
3. `composer install --no-dev`
4. `npm run build` (génère les assets)
5. Configurer le `.env` avec les vraies clés
6. Supprimer `Http::withoutVerifying()` (SSL valide en production)
7. Configurer un domaine et un certificat SSL (Let's Encrypt)

## Sécurité

Points à adresser avant mise en production :
- Supprimer `withoutVerifying()` et corriger le SSL proprement
- Ajouter une authentification (login/mot de passe) pour protéger l'app
- Limiter la taille des PDFs uploadés (actuellement 10Mo max)
- Valider le type MIME des fichiers uploadés côté serveur
- Stocker les credentials Google dans un endroit sécurisé (pas dans storage/app)
