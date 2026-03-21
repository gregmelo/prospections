# ProspectPro – Recherche d'entreprises pour la prospection

Application web Symfony 7 permettant de rechercher des entreprises françaises (et associations / administrations) à des fins de prospection commerciale, en s'appuyant sur l'API publique **recherche-entreprises.api.gouv.fr**.

L'interface offre un moteur de recherche multi‑critères (géographie, NAF, taille, forme juridique, année de création, etc.), l'exploration détaillée d'une fiche entreprise et l'export CSV des résultats.

---

## Sommaire

- [Fonctionnalités principales](#fonctionnalités-principales)
- [Architecture technique](#architecture-technique)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Lancement de l'application](#lancement-de-lapplication)
- [Utilisation](#utilisation)
  - [Recherche et filtres](#recherche-et-filtres)
  - [Tri des résultats](#tri-des-résultats)
  - [Statistiques / facettes](#statistiques--facettes)
  - [Favoris (client-side)](#favoris-client-side)
  - [Fiche entreprise détaillée](#fiche-entreprise-détaillée)
  - [Export CSV](#export-csv)
- [Détails sur l'intégration de l'API Recherche d'entreprises](#détails-sur-lintégration-de-lapi-recherche-dentreprises)
- [Structure du projet](#structure-du-projet)
- [Évolutions possibles](#évolutions-possibles)

---

## Fonctionnalités principales

- **Recherche d'entreprises** via l'API officielle `https://recherche-entreprises.api.gouv.fr/search`.
- **Filtres avancés** :
  - Code(s) postal(aux) du **siège social** (avec support de listes séparées par virgules),
  - Département(s),
  - Recherche texte libre (dénomination, adresse, dirigeants, élus, etc.),
  - Code NAF / APE (avec aide à la saisie),
  - Année de création (bornes min & max),
  - Tranche d'effectif salarié,
  - Forme juridique (EI, SARL/EURL, SAS/SASU, SA, association, etc.),
  - Exclusion des associations.
- **Tri des résultats** côté backend :
  - Nom (A → Z / Z → A),
  - Date de création (plus récentes / plus anciennes),
  - Tranche d'effectif salarié (croissant / décroissant).
- **Statistiques rapides (facettes)** sur la page de résultats :
  - Répartition par tranche d'effectif (sur les résultats de la page courante),
  - Top départements (basés sur le code postal du siège).
- **Fiche entreprise détaillée** : informations principales + liens utiles + dirigeants.
- **Export CSV** des entreprises trouvées.
- **UX renforcée** : auto-soumission des filtres, indicateur de chargement, affichage des filtres actifs, bouton "Copier le lien" de la recherche, favoris enregistrés dans le navigateur.

---

## Architecture technique

- **Framework** : Symfony 7.4 (Form, HttpClient, Twig, Validator, Console, etc.).
- **Langage** : PHP >= 8.2.
- **Front** : Twig + CSS custom (pas de framework JS lourd).
- **Données entreprises** : API `recherche-entreprises.api.gouv.fr` (aucune base locale).
- **DTO / Services principaux** :
  - `App\DTO\Company` : représentation d'une entreprise (SIREN, nom, activité principale, nature juridique, effectif, siège, dirigeants…).
  - `App\DTO\Dirigeant` : représentation d'un dirigeant (personne physique ou morale).
  - `App\DTO\SearchResult` : page de résultats (liste de Company + métadonnées de pagination + erreurs éventuelles).
  - `App\Service\CompanySearchService` : encapsulation des appels à l'API, normalisation des filtres, filtrage local et tri.
  - `App\Service\NafService` : dictionnaire des libellés NAF par divisions.
  - `App\Controller\SearchController` : orchestration des formulaires, affichage des résultats et export CSV.

---

## Prérequis

- PHP **8.2** ou plus récent.
- Composer (gestionnaire de dépendances PHP).
- Une extension web pour exécuter Symfony :
  - Soit le **Symfony CLI** (`symfony server:start`),
  - Soit un serveur web/PHP classique (Apache, Nginx, ou `php -S`).

Aucune base de données n'est requise : toutes les données proviennent de l'API publique.

---

## Installation

1. **Cloner le dépôt** :

```bash
git clone https://github.com/<owner>/prospections.git
cd prospections
```

2. **Installer les dépendances PHP** :

```bash
composer install
```

3. Vérifier que la version de PHP active est bien >= 8.2.

---

## Configuration

Le projet est relativement simple côté configuration :

- Aucune clé API n'est nécessaire pour `recherche-entreprises.api.gouv.fr`.
- Vous pouvez néanmoins utiliser les fichiers classiques de Symfony :
  - `.env` pour la configuration globale (mode dev/prod, etc.),
  - `config/` pour toutes les options framework, routes, services…

Le point d'entrée public est :

- `public/index.php`

Les routes sont principalement définies dans :

- `config/routes.yaml`
- `config/routes/`

---

## Lancement de l'application

Depuis la racine du projet :

### Avec Symfony CLI (recommandé)

```bash
symfony serve
```

ou

```bash
symfony server:start
```

L'application sera alors accessible généralement sur :

- `https://127.0.0.1:8000` ou `http://127.0.0.1:8000`

### Avec le serveur interne PHP

```bash
php -S 127.0.0.1:8000 -t public
```

Puis ouvrir un navigateur sur :

- `http://127.0.0.1:8000`

---

## Utilisation

### Recherche et filtres

La page principale (route `app_search`, `/`) affiche un formulaire de recherche construit via `App\Form\SearchType`.

Filtres disponibles :

- **Code(s) postal(aux)** (`code_postal`)
  - Texte libre, avec possibilité de saisir plusieurs codes séparés par des virgules, par ex. :
    - `69001`
    - `69001,69002,69003`
  - Un **filtrage local** garantit que le **siège social** de l'entreprise correspond bien à l'un des codes saisis.

- **Département(s)** (`departement`)
  - Là aussi, une liste séparée par des virgules est possible, par ex. : `01,69,13`.
  - Le filtrage local vérifie que le préfixe du code postal du siège appartient à la liste (ex. `69001` → département `69`).

- **Recherche libre** (`mots_cles`)
  - Terme textuel passé à l'API dans le paramètre `q` (nom, adresse, dirigeants, etc.).

- **Code NAF / APE** (`code_naf`)
  - Champ texte avec suggestions via une datalist dans le template.
  - Peut accepter plusieurs valeurs séparées par des virgules (l'API supporte les listes pour `activite_principale`).

- **Année de création** (`annee_creation_min` / `annee_creation_max`)
  - Filtre local appliqué sur le champ `date_creation` de l'entreprise (année extraite).
  - Contraintes de validation Symfony (entre 1900 et l'année courante).

- **Tranche d'effectif salarié** (`tranche_effectif`)
  - Liste déroulante avec les valeurs standard (00, 01, 02, … 51) et libellés lisibles.

- **Forme juridique** (`categorie_juridique`)
  - Choix typiques : EI, SARL/EURL, SAS/SASU, SA, association, autre.
  - Traduits vers les bons champs de l'API (par exemple `est_entrepreneur_individuel` ou `categorie_juridique_unite_legale`).

- **Exclure les associations** (`exclure_associations`)
  - Radio Oui/Non, valeur par défaut : Oui (1).
  - Se traduit en `est_association=false` côté API.

Le formulaire est soumis en méthode GET, ce qui permet :

- D'avoir des **URL partageables** représentant une recherche précise,
- D'implémenter des liens "Détails" qui embarquent les filtres, puis un retour à la recherche avec les mêmes critères.

### Tri des résultats

Un champ "Trier par" permet de choisir l'ordre d'affichage :

- Nom A → Z / Nom Z → A,
- Date de création (plus récentes en premier / plus anciennes en premier),
- Effectif salarié (croissant / décroissant).

Le tri est appliqué **côté serveur** dans `CompanySearchService::sortCompanies()` avant la pagination, que l'on soit :

- En mode "direct" (pagination native de l'API, sans filtres locaux d'année / géographie),
- Ou en mode "filtrage local" (lorsqu'on combine années de création et/ou géographie stricte du siège).

### Statistiques / facettes

Pour permettre un aperçu rapide du segment de prospection, la page de résultats affiche de petites facettes calculées à partir des `Company` de la page courante :

- **Répartition par effectif** (top quelques tranches),
- **Top départements** (d'après les codes postaux de siège).

Ces statistiques sont produites par la méthode privée `SearchController::buildStats()` et passées au template sous la variable `stats`.

### Favoris (client-side)

Chaque carte entreprise dans la liste de résultats possède un bouton étoile :

- Clic sur l'étoile → ajoute/retire le SIREN de la liste de favoris.
- Les favoris sont stockés dans `localStorage` (clé `prospection_favorites_sirens`).
- L'état visuel des étoiles est synchronisé à chaque chargement de page.

C'est une fonctionnalité purement **front-end** :

- Pas de persistance serveur,
- Les favoris sont propres au navigateur / appareil.

### Fiche entreprise détaillée

En cliquant sur "Détails →" sur une entreprise, vous accédez à la route `app_company_show` :

- Contrôleur : `SearchController::show()`
- Vue : `templates/search/company_show.html.twig`

La fiche affiche :

- Nom complet, SIREN,
- Adresse du siège, code postal et ville,
- Date de création,
- Forme juridique (libellée via `Company::getLibelleNatureJuridique()`),
- Secteur d'activité (libellé NAF via `NafService`),
- Libellé de tranche d'effectif,
- Liste des dirigeants personnes physiques (avec lien de recherche LinkedIn).

Le bouton "← Retour à la recherche" :

- Quand on vient d'une recherche, renvoie vers `app_search` **avec les mêmes paramètres GET** (filtres, page, tri…),
- Sinon, renvoie vers la page d'accueil de recherche sans filtres.

### Export CSV

Deux accès :

- Bouton d'export dans le panneau de filtres (en bas),
- Bouton d'export dans l'entête des résultats.

Route : `app_export_csv`

- Utilise `CompanySearchService::searchAll()` pour récupérer jusqu'à 500 entreprises correspondant aux filtres courants.
- Génère un CSV encodé en UTF‑8 avec BOM (compatibilité Excel).
- Colonnes typiques :
  - SIREN,
  - Nom de l'entreprise,
  - Sigle,
  - Date de création,
  - Adresse du siège,
  - Code postal, ville,
  - Code NAF + secteur d'activité,
  - Forme juridique,
  - Effectif,
  - Dirigeants (nom + qualité).

---

## Détails sur l'intégration de l'API Recherche d'entreprises

L'API utilisée est documentée dans `openapi.json` (copie de la spec OpenAPI). Les appels sont faits via :

- `CompanySearchService::fetch()` qui centralise :
  - la construction des requêtes HTTP (GET sur `/search`),
  - l'utilisation du `HttpClientInterface` de Symfony,
  - un cache applicatif (`CacheInterface`) pour limiter les appels répétitifs (TTL 5 minutes),
  - la gestion des erreurs.

### Modes de recherche

- **Recherche directe** (`searchDirect`) :
  - Utilisée lorsque **aucun filtre local** n'est nécessaire,
  - On délègue entièrement la pagination à l'API (`per_page`, `page`, `total_results`, `total_pages`).

- **Recherche avec filtrage local** (`searchWithLocalFilters`) :
  - Activée dès qu'il y a :
    - des bornes d'années de création, **et/ou**
    - un filtre de code postal, **et/ou**
    - un filtre de département.
  - Processus :
    1. Appel de l'API sur plusieurs pages (jusqu'à une limite `MAX_API_PAGES_PER_REQUEST`).
    2. Transformation des résultats bruts en `Company`.
    3. Application de `matchesLocalFilters()` pour :
       - filtrer par année de création,
       - filtrer par géographie du **siège** (code postal exact et/ou département),
    4. Tri éventuel selon le critère choisi.
    5. Pagination locale (découpage du tableau filtré par page de 25).

Pour éviter des charges trop lourdes :

- Un plafond `MAX_FILTERED_RESULTS` limite le nombre d'entreprises conservées (par exemple 1 000),
- Un flag `isTruncated` dans `SearchResult` signale au front que les résultats ont été tronqués (affichage d'un message "résultats tronqués, affinez vos filtres").

### Gestion des erreurs API

`SearchResult` possède :

- `hasError: bool` et `errorMessage: ?string`.

En cas d'erreur lors de l'appel HTTP ou du parsing JSON :

- `searchDirect()` renvoie un `SearchResult::error("Impossible de contacter l'API des entreprises.")`.
- `searchWithLocalFilters()` renvoie un `SearchResult::error(...)` si aucune entreprise n'a pu être récupérée **et** qu'une erreur est survenue.

Le template affiche alors un message dédié ("Erreur lors de la recherche"), distinct du cas "Aucun résultat".

---

## Structure du projet

Repères principaux :

- `public/`
  - `index.php` : point d'entrée HTTP.
- `src/`
  - `Controller/`
    - `SearchController.php` : pages de recherche, export CSV, fiche entreprise.
  - `DTO/`
    - `Company.php` : représentation d'une entreprise.
    - `Dirigeant.php` : représentation d'un dirigeant.
    - `SearchResult.php` : résultats paginés.
  - `Form/`
    - `SearchType.php` : formulaire principal de recherche.
  - `Service/`
    - `CompanySearchService.php` : logique d'appel API + filtres locaux + tri + export.
    - `NafService.php` : libellés NAF.
  - `Kernel.php` : classe noyau Symfony.
- `templates/`
  - `base.html.twig` : layout global.
  - `search/index.html.twig` : page de recherche + résultats.
  - `search/company_show.html.twig` : fiche détaillée entreprise.
- `config/`
  - `bundles.php`, `services.yaml`, `routes.yaml`, etc. : configuration Symfony standard.
- `openapi.json`, `swagger.json` : documentation de l'API utilisée.

---

## Évolutions possibles

Quelques pistes d'amélioration :

- **Filtre "Afficher uniquement mes favoris"** :
  - Masquer côté front les entreprises non favorites,
  - Ou introduire un petit stockage serveur par utilisateur (si authentification).
- **Exports additionnels** :
  - Export Excel (XLSX),
  - Formats d'export pré‑configurés pour certains CRM.
- **Filtres avancés API** :
  - Utiliser des filtres supplémentaires proposés par l'API (ESS, RGE, organismes de formation, etc.).
- **Persistance d'historiques de recherches** :
  - En base ou via un système de favoris de requêtes.
- **Intégration d'un système d'authentification** si l'outil est utilisé à plusieurs.

---

Ce README décrit le fonctionnement actuel de l'application et fournit les bases pour l'installer, la lancer et l'étendre. Pour toute question sur l'API sous‑jacente, se référer à la documentation officielle de `recherche-entreprises.api.gouv.fr` ou au fichier `openapi.json` présent dans le projet.
