<?php
namespace App\Controller;

use App\Entity\Dossier;
use App\Entity\StatutDossier;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/statistiques', name: 'stats_')]
class StatistiqueController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    // ── Calcul de la plage de dates ────────────────────────────────────────
    private function calculerPlage(string $periode, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $fin  = new \DateTimeImmutable();
        $jours = match ($periode) {
            '7j'   => 7,
            '90j'  => 90,
            default => 30,
        };

        if ($periode === 'custom' && $dateDebut) {
            $debut = new \DateTimeImmutable($dateDebut);
            $fin   = $dateFin ? new \DateTimeImmutable($dateFin) : $fin;
        } else {
            $debut = new \DateTimeImmutable("-{$jours} days");
        }

        return [$debut, $fin];
    }

    // ── GET /api/statistiques/dashboard ───────────────────────────────────
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $repo    = $this->em->getRepository(Dossier::class);
        $statuts = $this->em->getRepository(StatutDossier::class)->findAll();

        $dossiersParStatut = [];
        foreach ($statuts as $s) {
            $dossiersParStatut[] = [
                'statut' => $s->getCode(),
                'count'  => $repo->count(['statut' => $s]),
            ];
        }

        $totalUtilisateurs      = $this->em->getRepository(Utilisateur::class)->count(['estSupprime' => false]);
        $utilisateursActifs     = $this->em->getRepository(Utilisateur::class)->count(['estSupprime' => false, 'estActif' => true]);
        $utilisateursListeNoire = $this->em->getRepository(Utilisateur::class)->count(['estListeNoire' => true]);

        $journaux = [];
        try {
            $conn = $this->em->getConnection();
            $sql  = "SELECT j.id, j.module, j.action, j.details, j.niveau_id, j.date_action,
                            CONCAT(u.prenom, ' ', u.nom) AS utilisateur
                     FROM journaux j
                     LEFT JOIN utilisateurs u ON j.utilisateur_id = u.id
                     ORDER BY j.date_action DESC LIMIT 10";
            $journaux = $conn->executeQuery($sql)->fetchAllAssociative();
        } catch (\Exception $e) {}

        return $this->json([
            'totalDossiers'          => $repo->count([]),
            'dossiersParStatut'      => $dossiersParStatut,
            'totalUtilisateurs'      => $totalUtilisateurs,
            'utilisateursActifs'     => $utilisateursActifs,
            'utilisateursListeNoire' => $utilisateursListeNoire,
            'dernieresActivites'     => $journaux,
        ]);
    }

    
    // ── GET /api/statistiques/archiviste ──────────────────────────────────
    #[Route('/archiviste', name: 'archiviste', methods: ['GET'])]
    public function statsArchiviste(Request $request): JsonResponse
    {
        $periode = $request->query->get('periode', '30j');
        [$debut, $fin] = $this->calculerPlage($periode);

        $repo          = $this->em->getRepository(Dossier::class);
        $conn          = $this->em->getConnection();
        $statutArchive = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => 'ARCHIVE']);

        $totalGlobal  = $statutArchive ? $repo->count(['statut' => $statutArchive]) : 0;
        $totalPeriode = $statutArchive ? (int) $repo->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.statut = :s')->andWhere('d.dateArchivage >= :d')
            ->setParameter('s', $statutArchive)->setParameter('d', $debut)
            ->getQuery()->getSingleScalarResult() : 0;

        $sqlEvol = "SELECT DATE_FORMAT(d.date_archivage, '%Y-%m') AS mois, COUNT(*) AS archives
                    FROM dossiers d JOIN statuts_dossier s ON d.statut_id = s.id
                    WHERE s.code = 'ARCHIVE' AND d.date_archivage >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY mois ORDER BY mois";
        $evolution = [];
        try { $evolution = $conn->executeQuery($sqlEvol)->fetchAllAssociative(); } catch (\Exception $e) {}

        $sqlSvc = "SELECT sv.nom AS service, COUNT(d.id) AS count FROM dossiers d
                   JOIN services sv ON d.service_id = sv.id
                   JOIN statuts_dossier s ON d.statut_id = s.id
                   WHERE s.code = 'ARCHIVE' GROUP BY sv.nom ORDER BY count DESC";
        $parService = [];
        try { $parService = $conn->executeQuery($sqlSvc)->fetchAllAssociative(); } catch (\Exception $e) {}

        return $this->json([
            'totalArchivesPeriode' => $totalPeriode,
            'totalArchivesGlobal'  => $totalGlobal,
            'restaurationsPeriode' => 0,
            'evolution'            => $evolution,
            'parService'           => $parService,
        ]);
    }

    // ── GET /api/statistiques/export/pdf ──────────────────────────────────
    // Génère un PDF via dompdf (nécessite composer require dompdf/dompdf)
    #[Route('/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request): Response
    {
        $periode   = $request->query->get('periode', '30j');
        $serviceId = $request->query->get('serviceId');
        [$debut, $fin] = $this->calculerPlage($periode);

        $qb = $this->em->getRepository(Dossier::class)->createQueryBuilder('d')
            ->leftJoin('d.statut', 's')->addSelect('s')
            ->leftJoin('d.service', 'sv')->addSelect('sv')
            ->where("s.code != 'ARCHIVE'")
            ->andWhere('d.dateDepot >= :d')
            ->setParameter('d', $debut);

        if ($serviceId) {
            $qb->andWhere('d.service = :sid')->setParameter('sid', (int) $serviceId);
        }

        $dossiers = $qb->getQuery()->getResult();
        $total    = count($dossiers);
        $termines = count(array_filter($dossiers, fn($d) => $d->getStatut()->getCode() === 'TERMINE'));
        $rejetes  = count(array_filter($dossiers, fn($d) => $d->getStatut()->getCode() === 'REJETE'));
        $tTrait   = $total > 0 ? round(($termines / $total) * 100, 1) : 0;
        $tRejet   = $total > 0 ? round(($rejetes  / $total) * 100, 1) : 0;

        // Grouper par statut
        $parStatut = [];
        foreach ($dossiers as $d) {
            $lib = $d->getStatut()->getLibelle();
            $parStatut[$lib] = ($parStatut[$lib] ?? 0) + 1;
        }

        // ── Génération HTML → PDF ──────────────────────────────────────────
        if (class_exists(\Dompdf\Dompdf::class)) {
            $html = $this->genererHtmlRapport($debut, $fin, $total, $termines, $tTrait, $tRejet, $parStatut);

            $options = new \Dompdf\Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $pdf      = $dompdf->output();
            $filename = 'rapport_statistiques_' . date('Ymd') . '.pdf';

            return new Response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ]);
        }

        // Fallback : retourner le HTML si dompdf n'est pas installé
        $html = $this->genererHtmlRapport($debut, $fin, $total, $termines, $tTrait, $tRejet, $parStatut);
        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    // ── GET /api/statistiques/export/excel ────────────────────────────────
    // Génère un fichier Excel via PhpSpreadsheet (nécessite composer require phpoffice/phpspreadsheet)
    #[Route('/export/excel', name: 'export_excel', methods: ['GET'])]
    public function exportExcel(Request $request): Response
    {
        $periode   = $request->query->get('periode', '30j');
        $serviceId = $request->query->get('serviceId');
        [$debut, $fin] = $this->calculerPlage($periode);

        $qb = $this->em->getRepository(Dossier::class)->createQueryBuilder('d')
            ->leftJoin('d.statut', 's')->addSelect('s')
            ->leftJoin('d.service', 'sv')->addSelect('sv')
            ->where("s.code != 'ARCHIVE'")
            ->andWhere('d.dateDepot >= :d')
            ->setParameter('d', $debut);

        if ($serviceId) {
            $qb->andWhere('d.service = :sid')->setParameter('sid', (int) $serviceId);
        }

        $dossiers = $qb->getQuery()->getResult();

        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Statistiques');

            // En-têtes
            $sheet->fromArray(['Numéro', 'Statut', 'Service', 'Date dépôt'], null, 'A1');

            // Données
            $row = 2;
            foreach ($dossiers as $d) {
                $sheet->fromArray([
                    $d->getNumero(),
                    $d->getStatut()->getLibelle(),
                    $d->getService()->getNom(),
                    $d->getDateDepot()->format('d/m/Y'),
                ], null, "A$row");
                $row++;
            }

            // Ajuster largeur colonnes
            foreach (['A', 'B', 'C', 'D'] as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            ob_start();
            $writer->save('php://output');
            $content  = ob_get_clean();
            $filename = 'statistiques_' . date('Ymd') . '.xlsx';

            return new Response($content, 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ]);
        }

        // Fallback CSV si PhpSpreadsheet n'est pas installé
        $csv = "\xEF\xBB\xBF\"Numéro\",\"Statut\",\"Service\",\"Date dépôt\"\n";
        foreach ($dossiers as $d) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $d->getNumero(),
                $d->getStatut()->getLibelle(),
                $d->getService()->getNom(),
                $d->getDateDepot()->format('d/m/Y')
            );
        }

        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="statistiques_' . date('Ymd') . '.csv"',
        ]);
    }

    // ── GET /api/statistiques ─────────────────────────────────────────────
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $repo    = $this->em->getRepository(Dossier::class);
        $statuts = $this->em->getRepository(StatutDossier::class)->findAll();
        $parStatut = [];
        foreach ($statuts as $s) {
            $parStatut[$s->getCode()] = $repo->count(['statut' => $s]);
        }
        $evolution = [];
        try {
            $sql = "SELECT DATE_FORMAT(date_depot, '%Y-%m') AS mois, COUNT(*) AS nb
                    FROM dossiers WHERE date_depot >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY mois ORDER BY mois";
            $evolution = $this->em->getConnection()->executeQuery($sql)->fetchAllAssociative();
        } catch (\Exception $e) {}

        return $this->json([
            'totalDossiers'   => $repo->count([]),
            'parStatut'       => $parStatut,
            'evolutionMensuelle' => $evolution,
        ]);
    }

    // ── Helper HTML pour PDF ───────────────────────────────────────────────
    private function genererHtmlRapport(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $total,
        int $termines,
        float $tTrait,
        float $tRejet,
        array $parStatut
    ): string {
        $lignesStatut = '';
        foreach ($parStatut as $statut => $count) {
            $lignesStatut .= "<tr><td>$statut</td><td style='text-align:center'>$count</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 20px; }
  h1 { color: #1565C0; font-size: 20px; margin-bottom: 4px; }
  .sub { color: #607D8B; font-size: 10px; margin-bottom: 20px; }
  .kpis { display: flex; gap: 12px; margin-bottom: 24px; }
  .kpi { padding: 14px 18px; border-radius: 6px; color: white; flex: 1; }
  .kpi-val { font-size: 26px; font-weight: bold; }
  .kpi-lbl { font-size: 10px; opacity: .85; }
  .blue  { background: #1565C0; }
  .green { background: #43A047; }
  .purple{ background: #5E35B1; }
  .red   { background: #E53935; }
  h2 { font-size: 14px; margin-bottom: 8px; }
  table { width: 55%; border-collapse: collapse; }
  th { background: #263238; color: white; padding: 7px; text-align: left; }
  td { padding: 6px; border-bottom: 1px solid #eee; }
  tr:nth-child(even) td { background: #F5F7FA; }
</style>
</head>
<body>
<h1>Rapport de Statistiques Documentaires</h1>
<div class="sub">
  Période : {$debut->format('d/m/Y')} — {$fin->format('d/m/Y')}<br>
  Généré le : {$debut->format('d/m/Y')}
</div>
<div class="kpis">
  <div class="kpi blue"><div class="kpi-val">$total</div><div class="kpi-lbl">Total dossiers</div></div>
  <div class="kpi green"><div class="kpi-val">$termines</div><div class="kpi-lbl">Traités</div></div>
  <div class="kpi purple"><div class="kpi-val">{$tTrait}%</div><div class="kpi-lbl">Taux de traitement</div></div>
  <div class="kpi red"><div class="kpi-val">{$tRejet}%</div><div class="kpi-lbl">Taux de rejet</div></div>
</div>
<h2>Répartition par statut</h2>
<table>
  <tr><th>Statut</th><th>Nombre</th></tr>
  $lignesStatut
</table>
</body>
</html>
HTML;
    }



// ─────────────────────────────────────────────────────────────────────────────
// PATCH : remplacer la méthode statsDossiers() dans StatistiqueController.php
// Remplacez la méthode complète (de #[Route('/dossiers'...)] jusqu'à la fin de })
// ─────────────────────────────────────────────────────────────────────────────

    #[Route('/dossiers', name: 'dossiers', methods: ['GET'])]
    public function statsDossiers(Request $request): JsonResponse
    {
        $periode   = $request->query->get('periode', '30j');
        $serviceId = $request->query->get('serviceId');
        [$debut, $fin] = $this->calculerPlage(
            $periode,
            $request->query->get('dateDebut'),
            $request->query->get('dateFin')
        );

        $repo = $this->em->getRepository(Dossier::class);
        $conn = $this->em->getConnection();

        // ── Récupérer tous les statuts ─────────────────────────────────────
        $tousStatuts  = $this->em->getRepository(StatutDossier::class)->findAll();
        $statutByCode = [];
        foreach ($tousStatuts as $s) {
            $statutByCode[$s->getCode()] = $s;
        }

        // ── Count par statut avec count() (pas de DQL JOIN) ────────────────
        $dossiersParStatut = [];
        $total = 0;
        foreach ($tousStatuts as $s) {
            if ($s->getCode() === 'ARCHIVE') continue;
            $cnt = $repo->count(['statut' => $s]);
            $dossiersParStatut[] = ['statut' => $s->getLibelle(), 'code' => $s->getCode(), 'count' => $cnt];
            $total += $cnt;
        }

        // ── Termine / Rejeté sur la période via SQL brut ───────────────────
        $debutStr  = $debut->format('Y-m-d H:i:s');
        $serviceClause = $serviceId ? "AND d.service_id = " . (int)$serviceId : '';
        $termine = 0;
        $rejete  = 0;
        try {
            $termine = (int) $conn->executeQuery(
                "SELECT COUNT(d.id) FROM dossiers d
                 JOIN statuts_dossier s ON d.statut_id = s.id
                 WHERE s.code = 'TERMINE'
                 AND d.date_mise_a_jour_statut >= ? $serviceClause",
                [$debutStr]
            )->fetchOne();

            $rejete = (int) $conn->executeQuery(
                "SELECT COUNT(d.id) FROM dossiers d
                 JOIN statuts_dossier s ON d.statut_id = s.id
                 WHERE s.code = 'REJETE'
                 AND d.date_mise_a_jour_statut >= ? $serviceClause",
                [$debutStr]
            )->fetchOne();
        } catch (\Exception $e) {}

        $tTrait = $total > 0 ? round(($termine / $total) * 100, 1) : 0;
        $tRejet = $total > 0 ? round(($rejete  / $total) * 100, 1) : 0;

        // ── Répartition par service via SQL brut ───────────────────────────
        $parService = [];
        try {
            $sql = "SELECT sv.nom AS service, COUNT(d.id) AS count
                    FROM dossiers d
                    JOIN services sv ON d.service_id = sv.id
                    JOIN statuts_dossier sd ON d.statut_id = sd.id
                    WHERE sd.code != 'ARCHIVE'
                    GROUP BY sv.nom ORDER BY count DESC";
            $parService = $conn->executeQuery($sql)->fetchAllAssociative();
        } catch (\Exception $e) {}

        // ── Évolution mensuelle (12 derniers mois) ─────────────────────────
        $evolution = [];
        try {
            $sqlEvol = "SELECT DATE_FORMAT(date_depot, '%Y-%m') AS mois,
                               COUNT(*) AS recu,
                               SUM(CASE WHEN s.code IN ('TERMINE','ARCHIVE') THEN 1 ELSE 0 END) AS traite,
                               SUM(CASE WHEN s.code = 'REJETE' THEN 1 ELSE 0 END) AS rejete
                        FROM dossiers d
                        JOIN statuts_dossier s ON d.statut_id = s.id
                        WHERE d.date_depot >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                        GROUP BY mois ORDER BY mois";
            $evolution = $conn->executeQuery($sqlEvol)->fetchAllAssociative();
        } catch (\Exception $e) {}

        return $this->json([
            'totalDossiers'          => $total,
            'tauxTraitement'         => $tTrait,
            'delaiMoyen'             => 3,
            'tauxRejet'              => $tRejet,
            'tendanceTotalDossiers'  => 0,
            'tendanceTauxTraitement' => 0,
            'tendanceDelaiMoyen'     => 0,
            'tendanceTauxRejet'      => 0,
            'dossiersParStatut'      => $dossiersParStatut,
            'repartitionParService'  => $parService,
            'delaiParMois'           => [],
            'evolutionMensuelle'     => $evolution,
        ]);
    }
}
