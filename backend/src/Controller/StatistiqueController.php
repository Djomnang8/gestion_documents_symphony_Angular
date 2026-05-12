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

    private function pdfEscape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
    }

    private function genererPdfSimple(string $titre, array $lignes): string
    {
        $content = "BT
/F1 18 Tf
50 790 Td
(" . $this->pdfEscape($titre) . ") Tj
/F1 11 Tf
0 -28 Td
";
        foreach ($lignes as $ligne) {
            $content .= "(" . $this->pdfEscape((string) $ligne) . ") Tj
0 -16 Td
";
        }
        $content .= "ET";

        $objects = [];
        $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
";
        $objects[] = "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj
";
        $objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj
";
        $objects[] = "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj
";
        $objects[] = "5 0 obj << /Length " . strlen($content) . " >> stream
" . $content . "
endstream endobj
";

        $pdf = "%PDF-1.4
";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref
0 " . (count($objects) + 1) . "
0000000000 65535 f
";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n
", $offsets[$i]);
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>
startxref
{$xref}
%%EOF";

        return $pdf;
    }

    #[Route('/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request): Response
    {
        $contexte = $request->query->get('contexte', 'documentaire');
        $conn = $this->em->getConnection();
        $repo = $this->em->getRepository(Dossier::class);
        $date = (new \DateTimeImmutable())->format('d/m/Y H:i');
        $lignes = ["Genere le : {$date}", ""];

        if ($contexte === 'archivage') {
            $statutArchive = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => 'ARCHIVE']);
            $totalArchives = $statutArchive ? $repo->count(['statut' => $statutArchive]) : 0;
            $lignes[] = "Total archives : {$totalArchives}";
            $lignes[] = "";
            $lignes[] = "Archives par service :";
            try {
                $rows = $conn->executeQuery("SELECT sv.nom AS service, COUNT(d.id) AS count FROM dossiers d JOIN services sv ON d.service_id = sv.id JOIN statuts_dossier sd ON d.statut_id = sd.id WHERE sd.code = 'ARCHIVE' GROUP BY sv.nom ORDER BY count DESC")->fetchAllAssociative();
                foreach ($rows as $r) {
                    $lignes[] = "- {$r['service']} : {$r['count']}";
                }
            } catch (\Throwable $e) {
                $lignes[] = "Donnees par service indisponibles.";
            }
            $filename = 'rapport_archivage_' . date('Y-m-d') . '.pdf';
            $titre = 'Rapport de statistiques archivage';
        } else {
            $statuts = $this->em->getRepository(StatutDossier::class)->findAll();
            $total = 0;
            $lignes[] = "Repartition par statut :";
            foreach ($statuts as $s) {
                if ($s->getCode() === 'ARCHIVE') continue;
                $cnt = $repo->count(['statut' => $s]);
                $total += $cnt;
                $lignes[] = "- {$s->getLibelle()} : {$cnt}";
            }
            $lignes[] = "";
            $lignes[] = "Total dossiers actifs : {$total}";
            $lignes[] = "";
            $lignes[] = "Dossiers par service :";
            try {
                $rows = $conn->executeQuery("SELECT sv.nom AS service, COUNT(d.id) AS count FROM dossiers d JOIN services sv ON d.service_id = sv.id JOIN statuts_dossier sd ON d.statut_id = sd.id WHERE sd.code != 'ARCHIVE' GROUP BY sv.nom ORDER BY count DESC")->fetchAllAssociative();
                foreach ($rows as $r) {
                    $lignes[] = "- {$r['service']} : {$r['count']}";
                }
            } catch (\Throwable $e) {
                $lignes[] = "Donnees par service indisponibles.";
            }
            $filename = 'rapport_statistiques_' . date('Y-m-d') . '.pdf';
            $titre = 'Rapport de statistiques documentaires';
        }

        return new Response($this->genererPdfSimple($titre, $lignes), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/export/excel', name: 'export_excel', methods: ['GET'])]
    public function exportExcel(Request $request): Response
    {
        $conn = $this->em->getConnection();
        $repo = $this->em->getRepository(Dossier::class);

        $statuts    = $this->em->getRepository(StatutDossier::class)->findAll();
        $parStatut  = [];
        foreach ($statuts as $s) {
            $parStatut[$s->getLibelle()] = $repo->count(['statut' => $s]);
        }

        $parService = [];
        try {
            $parService = $conn->executeQuery(
                "SELECT sv.nom AS service, COUNT(d.id) AS count FROM dossiers d
                 JOIN services sv ON d.service_id = sv.id
                 GROUP BY sv.nom ORDER BY count DESC"
            )->fetchAllAssociative();
        } catch (\Throwable $e) {}

        // Générer CSV UTF-8 (compatible Excel)
        $bom = "\xEF\xBB\xBF"; // BOM UTF-8 pour Excel
        $csv = $bom;
        $csv .= "Rapport Statistiques — " . date('d/m/Y') . "\r\n\r\n";
        $csv .= "RÉPARTITION PAR STATUT\r\n";
        $csv .= "Statut;Nombre\r\n";
        foreach ($parStatut as $lib => $cnt) {
            $csv .= "{$lib};{$cnt}\r\n";
        }
        $csv .= "\r\nRÉPARTITION PAR SERVICE\r\n";
        $csv .= "Service;Nombre\r\n";
        foreach ($parService as $r) {
            $csv .= "{$r['service']};{$r['count']}\r\n";
        }

        return new Response($csv, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
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
