<?php

namespace App\Service;

use App\DTO\Company;
use App\DTO\SearchResult;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CompanySearchService
{
    private const API_URL  = 'https://recherche-entreprises.api.gouv.fr/search';
    private const PER_PAGE = 25;
    // Nombre maximum de pages API à parcourir pour constituer une page de résultats filtrés
    private const MAX_API_PAGES_PER_REQUEST = 30;
    // Nombre maximal d'entreprises à conserver lorsqu'on applique des filtres locaux
    private const MAX_FILTERED_RESULTS = 1000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {}

    /**
     * @param array<string, mixed> $filters
     */
    public function search(array $filters, int $page = 1): SearchResult
    {
        $filters = $this->normalizeFilters($filters);
        $sort = $filters['sort'] ?? null;

        // Filtrage local pour :
        //  - les bornes d'année de création
        //  - la géographie basée sur le siège (code postal / département)
        $hasLocalFilter =
            !empty($filters['annee_creation_min'])
            || !empty($filters['annee_creation_max'])
            || !empty($filters['code_postal'])
            || !empty($filters['departement']);

        // Sans filtre local : déléguer entièrement à l'API (rapide, pagination native)
        if (!$hasLocalFilter) {
            return $this->searchDirect($filters, $page, $sort);
        }

        // Avec filtre local (géographique ou temporel)
        return $this->searchWithLocalFilters($filters, $page, $sort);
    }

    /**
     * Recherche directe sans filtrage local post-API.
     */
    private function searchDirect(array $filters, int $page, ?string $sort = null): SearchResult
    {
        $params = $this->buildParams($filters, $page);

        try {
            $data = $this->fetch($params, 15);
        } catch (\Throwable) {
            return SearchResult::error("Impossible de contacter l'API des entreprises.");
        }

        $companies    = array_map(fn(array $i) => Company::fromArray($i), $data['results'] ?? []);
        $companies    = $this->sortCompanies($companies, $sort);
        $totalResults = $data['total_results'] ?? count($companies);
        $totalPages   = $data['total_pages']   ?? max(1, (int) ceil($totalResults / self::PER_PAGE));

        return new SearchResult(
            companies:    $companies,
            totalResults: $totalResults,
            page:         $data['page'] ?? $page,
            perPage:      self::PER_PAGE,
            totalPages:   $totalPages,
        );
    }

    /**
     * Pagination adaptative avec filtrage local.
     */
    private function searchWithLocalFilters(array $filters, int $page, ?string $sort = null): SearchResult
    {
        $filtered    = [];
        $apiPage     = 1;
        $params      = $this->buildParams($filters, 1);
        $maxApiPages = self::MAX_API_PAGES_PER_REQUEST;
        $hadError    = false;
        $isTruncated = false;

        // Nombre de résultats dont on a réellement besoin pour couvrir la page demandée
        $neededForPage = max(self::PER_PAGE, $page * self::PER_PAGE + 2 * self::PER_PAGE);
        $maxCollected  = min(self::MAX_FILTERED_RESULTS, $neededForPage);

        // On parcourt un nombre limité de pages API, mais de manière indépendante
        // de la page demandée, pour garder un total cohérent entre les pages.
        while ($apiPage <= $maxApiPages) {
            $params['page'] = $apiPage;

            try {
                $data = $this->fetch($params, 20);
            } catch (\Throwable) {
                $hadError = true;
                break;
            }

            $raw = $data['results'] ?? [];
            if (empty($raw)) {
                break;
            }

            foreach ($raw as $item) {
                $company = Company::fromArray($item);
                if ($this->matchesLocalFilters($company, $filters)) {
                    $filtered[] = $company;
                    if (count($filtered) >= $maxCollected) {
                        $isTruncated = true;
                        break 2; // On a assez de résultats pour paginer correctement
                    }
                }
            }

            $totalPagesApi = $data['total_pages'] ?? 1;
            if ($apiPage >= $totalPagesApi) {
                break; // Plus de pages API
            }

            $apiPage++;
        }

        $filteredCount = count($filtered);

        if ($filteredCount === 0) {
            if ($hadError) {
                return SearchResult::error("Une erreur est survenue lors de la récupération des données.");
            }

            return SearchResult::empty();
        }

        $filtered      = $this->sortCompanies($filtered, $sort);
        $totalResults = $filteredCount;
        $totalPages   = max(1, (int) ceil($totalResults / self::PER_PAGE));

        // Normalisation de la page demandée pour éviter des incohérences (page > totalPages)
        if ($page < 1) {
            $page = 1;
        } elseif ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset    = ($page - 1) * self::PER_PAGE;
        $pageItems = array_slice($filtered, $offset, self::PER_PAGE);

        return new SearchResult(
            companies:    $pageItems,
            totalResults: $totalResults,
            page:         $page,
            perPage:      self::PER_PAGE,
            totalPages:   $totalPages,
            hasError:     false,
            errorMessage: null,
            isTruncated:  $isTruncated,
        );
    }

    /**
     * Vérifie les filtres géographiques et les filtres de date ignorés par l'API.
     */
    private function matchesLocalFilters(Company $company, array $filters): bool
    {
        // Filtrage local sur la date de création
        $dateCreation = $company->dateCreation; // format "YYYY-MM-DD"
        if ($dateCreation) {
            $anneeCreation = (int) substr($dateCreation, 0, 4);
            
            if (!empty($filters['annee_creation_min']) && $anneeCreation < (int)$filters['annee_creation_min']) {
                return false;
            }
            if (!empty($filters['annee_creation_max']) && $anneeCreation > (int)$filters['annee_creation_max']) {
                return false;
            }
        } elseif (!empty($filters['annee_creation_min']) || !empty($filters['annee_creation_max'])) {
            // Date inconnue mais filtre exigé
            return false;
        }

        // Filtrage local sur la géographie du siège (code postal / département)
        $siegeCp = $company->getCodePostalSiege();

        if (!empty($filters['code_postal'])) {
            $allowedCps = array_filter(array_map('trim', explode(',', (string) $filters['code_postal'])), static fn(string $v) => $v !== '');
            if ($siegeCp === '' || !in_array($siegeCp, $allowedCps, true)) {
                return false;
            }
        }

        if (!empty($filters['departement'])) {
            $allowedDeps = array_filter(array_map('trim', explode(',', (string) $filters['departement'])), static fn(string $v) => $v !== '');
            if ($siegeCp === '') {
                return false;
            }
            $dep = substr($siegeCp, 0, 2);
            if (!in_array($dep, $allowedDeps, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildParams(array $filters, int $page): array
    {
        $params = [
            'per_page' => self::PER_PAGE,
            'page'     => $page,
        ];

        // Recherche textuelle
        if (!empty($filters['mots_cles'])) {
            $params['q'] = $filters['mots_cles'];
        }

        // Code postal : transmis à l'API pour pré-filtrage (large), affinage local ensuite
        if (!empty($filters['code_postal'])) {
            $params['code_postal'] = $filters['code_postal'];
        }

        // Département : même logique
        if (!empty($filters['departement'])) {
            $params['departement'] = $filters['departement'];
        }

        // Code NAF / APE
        if (!empty($filters['code_naf'])) {
            $params['activite_principale'] = $filters['code_naf'];
        }

        // Tranche d'effectif salarié (valeur unique uniquement)
        if (!empty($filters['tranche_effectif'])) {
            $params['tranche_effectif_salarie_unite_legale'] = $filters['tranche_effectif'];
        }

        // Forme juridique
        if (!empty($filters['categorie_juridique'])) {
            $val = $filters['categorie_juridique'];
            if ($val === 'EI') {
                $params['est_entrepreneur_individuel'] = 'true';
            } else {
                $params['categorie_juridique_unite_legale'] = $val;
            }
        }

        // Exclure les associations
        if (!empty($filters['exclure_associations'])) {
            $params['est_association'] = 'false';
        }

        // Uniquement les entreprises actives
        $params['etat_administratif_unite_legale'] = 'A';

        return $params;
    }

    /**
     * Récupère tous les résultats pour l'export CSV (max 500 entreprises).
     * @param array<string, mixed> $filters
     * @return Company[]
     */
    public function searchAll(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $hasGeoFilter = !empty($filters['code_postal']) || !empty($filters['departement']);

        if (!$hasGeoFilter) {
            // Sans filtre géo, pagination API directe
            $all     = [];
            $page    = 1;
            $maxPages = 20;
            do {
                $result = $this->searchDirect($filters, $page, null);
                $all    = array_merge($all, $result->companies);
                $page++;
            } while ($page <= $result->totalPages && $page <= $maxPages);
            return $all;
        }

        // Avec filtre géo, on accumule en filtrant localement (max 500)
        $all     = [];
        $apiPage = 1;
        $maxPages = 20;
        $params  = $this->buildParams($filters, 1);

        while (count($all) < 500 && $apiPage <= $maxPages) {
            $params['page'] = $apiPage;
            try {
                $data = $this->fetch($params, 15);
            } catch (\Throwable) {
                break;
            }

            $raw = $data['results'] ?? [];
            if (empty($raw)) {
                break;
            }

            foreach ($raw as $item) {
                $company = Company::fromArray($item);
                if ($this->matchesLocalFilters($company, $filters)) {
                    $all[] = $company;
                }
            }

            if ($apiPage >= ($data['total_pages'] ?? 1)) {
                break;
            }
            $apiPage++;
        }

        return $all;
    }

    public function findBySiren(string $siren): ?Company
    {
        $siren = trim($siren);
        if ($siren === '') {
            return null;
        }

        $params = [
            'q'      => $siren,
            'page'   => 1,
            'per_page' => 5,
            'etat_administratif_unite_legale' => 'A',
        ];

        try {
            $data = $this->fetch($params, 15);
        } catch (\Throwable) {
            return null;
        }

        foreach ($data['results'] ?? [] as $item) {
            if (($item['siren'] ?? null) === $siren) {
                return Company::fromArray($item);
            }
        }

        return null;
    }

    /**
     * Normalise les filtres texte pouvant contenir des listes séparées par des virgules.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        foreach (['code_postal', 'departement', 'code_naf'] as $key) {
            if (!isset($filters[$key]) || !is_string($filters[$key])) {
                continue;
            }

            $value = trim($filters[$key]);
            if ($value === '') {
                $filters[$key] = '';
                continue;
            }

            // Nettoyage des listes séparées par des virgules
            $parts = array_filter(array_map('trim', explode(',', $value)), static fn(string $v) => $v !== '');
            $filters[$key] = implode(',', $parts);
        }

        return $filters;
    }

    /**
     * Récupère la réponse API avec un petit cache pour limiter les appels.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function fetch(array $params, int $timeout): array
    {
        ksort($params);
        $cacheKey = 'company_search_' . md5(http_build_query($params));

        /** @var array<string, mixed> $data */
        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($params, $timeout) {
            $item->expiresAfter(300);

            $response = $this->httpClient->request('GET', self::API_URL, [
                'query'   => $params,
                'timeout' => $timeout,
            ]);

            return $response->toArray();
        });

        return $data;
    }

    /**
     * Trie les entreprises selon le critère désiré.
     *
     * @param Company[] $companies
     * @return Company[]
     */
    private function sortCompanies(array $companies, ?string $sort): array
    {
        if ($sort === null || $sort === '') {
            return $companies;
        }

        usort($companies, function (Company $a, Company $b) use ($sort): int {
            return match ($sort) {
                'name_asc'  => strcasecmp($a->nomComplet, $b->nomComplet),
                'name_desc' => strcasecmp($b->nomComplet, $a->nomComplet),
                'date_asc'  => $this->compareDates($a->dateCreation, $b->dateCreation),
                'date_desc' => $this->compareDates($b->dateCreation, $a->dateCreation),
                'effectif_asc'  => $this->getEffectifRank($a->trancheEffectifSalarie) <=> $this->getEffectifRank($b->trancheEffectifSalarie),
                'effectif_desc' => $this->getEffectifRank($b->trancheEffectifSalarie) <=> $this->getEffectifRank($a->trancheEffectifSalarie),
                default => 0,
            };
        });

        return $companies;
    }

    private function compareDates(?string $a, ?string $b): int
    {
        if ($a === null && $b === null) {
            return 0;
        }
        if ($a === null) {
            return 1; // dates nulles en dernier
        }
        if ($b === null) {
            return -1;
        }

        return strcmp($a, $b);
    }

    private function getEffectifRank(?string $code): int
    {
        if ($code === null || $code === '' || $code === 'NN') {
            return PHP_INT_MAX;
        }

        // Ordre croissant correspondant aux tranches connues
        $order = [
            '00', '01', '02', '03', '11', '12', '21', '22', '31', '32', '41', '42', '51',
        ];

        $index = array_search($code, $order, true);
        if ($index === false) {
            return PHP_INT_MAX - 1;
        }

        return $index;
    }
}
