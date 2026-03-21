<?php

namespace App\Controller;

use App\Form\SearchType;
use App\Service\CompanySearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly CompanySearchService $searchService,
    ) {}

    #[Route('/', name: 'app_search')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(SearchType::class);
        $form->handleRequest($request);

        $result   = null;
        $searched = false;
        $filters  = [];

        // Déclenche la recherche dès qu'au moins un paramètre est présent dans l'URL
        $searchParams = ['code_postal', 'departement', 'mots_cles', 'code_naf',
                 'annee_creation_min', 'annee_creation_max',
                 'tranche_effectif', 'categorie_juridique', 'sort'];

        $hasSearchParam = false;
        foreach ($searchParams as $param) {
            if ($request->query->has($param) && $request->query->get($param) !== '') {
                $hasSearchParam = true;
                break;
            }
        }

        if ($hasSearchParam || ($form->isSubmitted() && $form->isValid())) {
            $searched = true;
            $page     = max(1, (int) $request->query->get('page', 1));

            // La valeur de exclure_associations est maintenant '1' ou '0'
            $exclureAssociations = $request->query->get('exclure_associations', '1') === '1';

            $filters = [
                'code_postal'          => trim($request->query->get('code_postal', '')),
                'departement'          => trim($request->query->get('departement', '')),
                'mots_cles'            => trim($request->query->get('mots_cles', '')),
                'code_naf'             => trim($request->query->get('code_naf', '')),
                'annee_creation_min'   => $request->query->get('annee_creation_min', ''),
                'annee_creation_max'   => $request->query->get('annee_creation_max', ''),
                'tranche_effectif'     => $request->query->get('tranche_effectif', ''),
                'categorie_juridique'  => $request->query->get('categorie_juridique', ''),
                'sort'                 => $request->query->get('sort', ''),
                'exclure_associations' => $exclureAssociations,
                'page'                 => $page,
            ];

            $result = $this->searchService->search($filters, $page);
        }

        $stats = null;
        if ($result !== null && !$result->hasError && $result->totalResults > 0) {
            $stats = $this->buildStats($result->companies);
        }

        return $this->render('search/index.html.twig', [
            'form'     => $form->createView(),
            'result'   => $result,
            'searched' => $searched,
            'filters'  => $filters,
            'stats'    => $stats,
        ]);
    }

    #[Route('/export/csv', name: 'app_export_csv')]
    public function exportCsv(Request $request): StreamedResponse
    {
        $exclureAssociations = $request->query->get('exclure_associations', '1') === '1';

        $filters = [
            'code_postal'          => trim($request->query->get('code_postal', '')),
            'departement'          => trim($request->query->get('departement', '')),
            'mots_cles'            => trim($request->query->get('mots_cles', '')),
            'code_naf'             => trim($request->query->get('code_naf', '')),
            'annee_creation_min'   => $request->query->get('annee_creation_min', ''),
            'annee_creation_max'   => $request->query->get('annee_creation_max', ''),
            'tranche_effectif'     => $request->query->get('tranche_effectif', ''),
            'categorie_juridique'  => $request->query->get('categorie_juridique', ''),
            'exclure_associations' => $exclureAssociations,
        ];

        $companies = $this->searchService->searchAll($filters);

        $response = new StreamedResponse(function () use ($companies) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour compatibilité Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'SIREN',
                'Nom de l\'entreprise',
                'Sigle',
                'Date de création',
                'Adresse siège',
                'Code postal',
                'Ville',
                'Code NAF',
                'Secteur activité',
                'Forme juridique',
                'Effectif',
                'Dirigeants',
            ], ';');

            foreach ($companies as $company) {
                $dirigeants = array_map(
                    fn($d) => $d->getNomComplet() . ' (' . ($d->qualite ?? 'N/A') . ')',
                    $company->getDirigeantsPhysiques()
                );

                fputcsv($handle, [
                    $company->siren,
                    $company->nomComplet,
                    $company->sigle ?? '',
                    $company->dateCreation ?? '',
                    $company->getAdresseSiege(),
                    $company->getCodePostalSiege(),
                    $company->getVilleSiege(),
                    $company->activitePrincipale ?? '',
                    $company->getLibelleActivitePrincipale(),
                    $company->getLibelleNatureJuridique(),
                    $company->getLibelleEffectif(),
                    implode(' | ', $dirigeants),
                ], ';');
            }

            fclose($handle);
        });

        $filename = 'prospection_' . date('Y-m-d_His') . '.csv';

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Affiche une fiche détaillée pour une entreprise donnée.
     */
    #[Route('/entreprise/{siren}', name: 'app_company_show')]
    public function show(string $siren, Request $request): Response
    {
        $company = $this->searchService->findBySiren($siren);

        if (!$company) {
            throw $this->createNotFoundException('Entreprise introuvable.');
        }

        return $this->render('search/company_show.html.twig', [
            'company' => $company,
            'from'    => $request->query->all(),
        ]);
    }

    /**
     * @param array<int, \App\DTO\Company> $companies
     * @return array<string, array<string,int>>
     */
    private function buildStats(array $companies): array
    {
        $byEffectif    = [];
        $byDepartement = [];

        foreach ($companies as $company) {
            $effectifLabel = $company->getLibelleEffectif();
            if ($effectifLabel !== 'Non renseigné') {
                $byEffectif[$effectifLabel] = ($byEffectif[$effectifLabel] ?? 0) + 1;
            }

            $cp = $company->getCodePostalSiege();
            if ($cp !== '') {
                $dep = substr($cp, 0, 2);
                $byDepartement[$dep] = ($byDepartement[$dep] ?? 0) + 1;
            }
        }

        arsort($byEffectif);
        arsort($byDepartement);

        return [
            'by_effectif'    => $byEffectif,
            'by_departement' => $byDepartement,
        ];
    }
}
