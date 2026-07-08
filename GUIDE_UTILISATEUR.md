# Guide utilisateur — Jobsecu Catalogue EPI

## Qu'est-ce que cette application ?

Cette application vous permet d'importer automatiquement des fiches techniques PDF de vos produits dans un catalogue Google Sheets, puis de générer un fichier CSV prêt à importer dans WooCommerce.

**Sans l'application :** 15 minutes de saisie manuelle par produit
**Avec l'application :** 2-3 minutes par produit (vérification uniquement)

---

## Avant de commencer

Vous aurez besoin de :
- Une clé API Anthropic (Claude) — voir section "Obtenir une clé API"
- L'application installée et lancée par votre prestataire

---

## Obtenir une clé API Claude

1. Rendez-vous sur [console.anthropic.com](https://console.anthropic.com)
2. Créez un compte ou connectez-vous
3. Allez dans **API Keys** → **Create Key**
4. Copiez la clé (commence par `sk-ant-...`)
5. Transmettez-la à votre prestataire pour configuration

**Coût par fichier** --> 0.01€
**Coût estimé :** ~0,20€ pour traiter 190 fiches produits

---

## Utilisation pas à pas

### Étape 1 — Choisir la famille de produits

Dans le menu déroulant en haut, sélectionnez l'onglet correspondant à vos produits :

- Produits Pieds
- Produits Têtes
- Produits Mains
- etc.

### Étape 2 — Déposer les fiches PDF

Glissez vos fichiers PDF dans la zone pointillée, ou cliquez dessus pour les sélectionner.

✅ Vous pouvez déposer **plusieurs PDFs en même temps**
✅ Les fiches doivent être des **PDFs lisibles** (pas des scans)

### Étape 3 — Lancer l'import

Cliquez sur **"Lancer l'import"**. Une barre de progression s'affiche.

Pour chaque produit vous verrez :
- ✅ **Mis à jour** — les données ont été ajoutées dans le Sheet
- ➕ **Ajouté** — nouveau produit ajouté en bas du Sheet
- ❌ **Erreur** — problème avec ce PDF (voir le message)

### Étape 4 — Vérifier dans Google Sheets

Ouvrez votre Google Sheet. Vous verrez les colonnes colorées :

| Couleur | Signification |
|---|---|
| 🟢 Vert | Donnée trouvée et remplie automatiquement |
| 🟡 Jaune | Donnée manquante — à compléter manuellement |
| 🟠 Orange | Alerte — vérification recommandée |

**Vérifiez notamment :**
- Les cellules jaunes (données non trouvées dans le PDF)
- La colonne **Alertes** (AK) pour les messages importants
- Le **Type** (Haute/Basse) — parfois mal détecté

### Étape 5 — Compléter votre catalogue

Pour chaque produit, complétez les colonnes que l'application ne peut pas remplir automatiquement :

**Colonnes à compléter par vous :**
- **Sous-catégories** (G, H, I, J) — ex: "Chaussures extérieur", "Chaussures femme"
- **Métiers** (AC à AH) — ex: "BTP", "Logistique"
- **Images** (B) — nom du fichier image

### Étape 6 — Exporter le CSV WooCommerce

Une fois votre catalogue complet et vérifié, cliquez sur **"Exporter CSV WooCommerce"**.

Un fichier `jobsecu-produits-pieds.csv` se télécharge automatiquement.

### Étape 7 — Importer dans WordPress

1. Dans WordPress, allez dans **WooCommerce → Produits → Importer**
2. Chargez le fichier CSV téléchargé
3. Vérifiez le mapping des colonnes
4. Lancez l'import

---

## Questions fréquentes

**Q : Un produit n'a pas été trouvé dans le Sheet ?**
Il a été ajouté en bas du Sheet. Vérifiez les dernières lignes et déplacez-le à la bonne position si nécessaire.

**Q : Le type Haute/Basse est incorrect ?**
Corrigez-le directement dans le Sheet avant d'exporter le CSV.

**Q : La norme est marquée en alerte ?**
Certaines normes spéciales (ex: EN ISO 20349 pour soudeurs) ne sont pas dans la liste standard. Vérifiez et ajustez si nécessaire.

**Q : Puis-je retraiter un PDF déjà importé ?**
Oui — l'application met à jour les données sans écraser ce que vous avez rempli manuellement.

**Q : Combien de PDFs puis-je traiter à la fois ?**
Autant que vous voulez — l'application les traite un par un automatiquement. 20 PDFs prennent environ 2-3 minutes.
