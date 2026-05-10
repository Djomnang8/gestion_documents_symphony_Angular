<?php
namespace App\Controller;

use App\Entity\Dossier;
use App\Entity\HistoriqueStatut;
use App\Entity\Journal;
use App\Entity\Notification;
use App\Entity\StatutDossier;
use App\Entity\Utilisateur;
use App\Entity\VersionDocument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/archivage', name: 'archivage_')]
class ArchivageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security
    ) {}

    // ── HELPER : notifier tous les archivistes ─────────────────────────────
    private function notifierArchivistes(
        string $titre,
        string $message,
        string $type,
        ?string $dossierId = null,
        ?string $numeroDossier = null
    ): void {
        $archivistes = $this->em->getRepository(Utilisateur::class)
            ->createQueryBuilder('u')
            ->where('u.typeUtilisateur = :type')
            ->andWhere('u.estActif = true')
            ->andWhere('u.estListeNoire = false')
            ->andWhere('u.estSupprime = false')
            ->setParameter('type', 'Archiviste')
            ->getQuery()->getResult();

        foreach ($archivistes as $archiviste) {
            $notif = new Notification();
            $notif->setUtilisateur($archiviste)
                  ->setTitre($titre)
                  ->setMessage($message)
                  ->setType($type)
                  ->setDossierId($dossierId)
                  ->setNumeroDossier($numeroDossier);
            $this->em->persist($notif);
        }
        $this->em->flush();
    }

    // ── HELPER : journaliser une action ────────────────────────────────────
    private function journaliser(string $action, string $details, ?string $entiteId = null): void
    {
        $user = $this->security->getUser();
        $journal = new Journal();
        $journal->setModule('Archivage')
                ->setAction($action)
                ->setDetails($details)
                ->setNiveauId(1)
                ->setEntiteId($entiteId);
        if ($user instanceof Utilisateur) {
            $journal->setUtilisateur($user);
        }
        $this->em->persist($journal);
        $this->em->flush();
    }

    // ── GET /api/archivage/kpi ─────────────────────────────────────────────
    #[Route('/kpi', name: 'kpi', methods: ['GET'])]
    public function kpi(): JsonResponse
    {
        $repo          = $this->em->getRepository(Dossier::class);
        $statutArchive = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => 'ARCHIVE']);
        $statutTermine = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => 'TERMINE']);

        $aArchiver     = $statutTermine ? $repo->count(['statut' => $statutTermine]) : 0;
        $totalArchives = $statutArchive ? $repo->count(['statut' => $statutArchive]) : 0;

        $debutMois      = new \DateTimeImmutable('first day of this month midnight');
        $archivesCeMois = 0;
        if ($statutArchive) {
            $archivesCeMois = (int) $repo->createQueryBuilder('d')
                ->select('COUNT(d.id)')
                ->where('d.statut = :s')
                ->andWhere('d.dateArchivage >= :debut')
                ->setParameter('s', $statutArchive)
                ->setParameter('debut', $debutMois)
                ->getQuery()->getSingleScalarResult();
        }

        return $this->json([
            'aArchiver'      => $aArchiver,
            'archivesCeMois' => $archivesCeMois,
            'totalArchives'  => $totalArchives,
        ]);
    }

    
// ─────────────────────────────────────────────────────────────────────────────
// PATCH ArchivageController — remplacer la méthode aArchiver()
// ─────────────────────────────────────────────────────────────────────────────

    #[Route('/a-archiver', name: 'a_archiver', methods: ['GET'])]
    public function aArchiver(): JsonResponse
    {
        $conn = $this->em->getConnection();

        try {
            $rows = $conn->executeQuery(
                "SELECT d.id, d.numero, d.titre, d.nom_citoyen,
                        d.date_depot, d.date_mise_a_jour_statut,
                        sv.nom AS service_nom,
                        COUNT(v.id) AS nb_documents
                 FROM dossiers d
                 JOIN statuts_dossier s ON d.statut_id = s.id
                 LEFT JOIN services sv ON d.service_id = sv.id
                 LEFT JOIN versions_document v ON v.dossier_id = d.id
                 WHERE s.code = 'TERMINE'
                 GROUP BY d.id, d.numero, d.titre, d.nom_citoyen,
                          d.date_depot, d.date_mise_a_jour_statut, sv.nom
                 ORDER BY d.date_mise_a_jour_statut ASC"
            )->fetchAllAssociative();

            return $this->json(array_map(fn($r) => [
                'id'                  => $r['id'],
                'numero'              => $r['numero'],
                'titre'               => $r['titre'],
                'nomCitoyen'          => $r['nom_citoyen'],
                'serviceNom'          => $r['service_nom'],
                'dateDepot'           => $r['date_depot'],
                'dateMiseAJourStatut' => $r['date_mise_a_jour_statut'],
                'nbDocuments'         => (int) $r['nb_documents'],
            ], $rows));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── POST /api/archivage/{dossierId} ─────────────────────────────────────
    // Archivage avec fusion si même email citoyen (identique à C#)
        #[Route('/{dossierId}', name: 'archiver', methods: ['POST'])]
public function archiver(string $dossierId): JsonResponse
{
    $dossier = $this->em->getRepository(Dossier::class)->find($dossierId);
    if (!$dossier) {
        return $this->json(['error' => 'Dossier introuvable.'], 404);
    }

    if ($dossier->getStatut()->getCode() !== 'TERMINE') {
        return $this->json(['error' => 'Seuls les dossiers terminés peuvent être archivés.'], 400);
    }

    $statutArchive = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => 'ARCHIVE']);
    if (!$statutArchive) {
        return $this->json(['error' => "Statut 'ARCHIVE' non défini."], 400);
    }

    $ancienStatut = $dossier->getStatut();

    $historique = new HistoriqueStatut();
    $historique->setDossier($dossier)
               ->setAncienStatut($ancienStatut)
               ->setNouveauStatut($statutArchive)
               ->setCommentaire('Archivage définitif')
               ->setAgent($this->security->getUser() instanceof Utilisateur ? $this->security->getUser() : null);

    $dossier->setStatut($statutArchive)
            ->setDateArchivage(new \DateTimeImmutable())
            ->setDateMiseAJourStatut(new \DateTimeImmutable());

    $this->em->persist($historique);

    try {
        $this->em->flush();
    } catch (\Exception $e) {
        return $this->json(['error' => 'Erreur lors de l\'archivage : ' . $e->getMessage()], 500);
    }

    // Journalisation
    $this->journaliser('ARCHIVAGE', 'ARCHIVAGE_NOUVEAU',
        sprintf('Dossier %s archivé.', $dossier->getNumero()), $dossier->getId());

    // Notification aux archivistes
    $archivistes = $this->em->getRepository(Utilisateur::class)
        ->createQueryBuilder('u')
        ->where('u.typeUtilisateur = :type')
        ->andWhere('u.estActif = true')
        ->setParameter('type', 'Archiviste')
        ->getQuery()->getResult();

    foreach ($archivistes as $archiviste) {
        $notif = new Notification();
        $notif->setUtilisateur($archiviste)
              ->setTitre("Nouvelle archive")
              ->setMessage("Le dossier {$dossier->getNumero()} a été archivé.")
              ->setType('ARCHIVAGE')
              ->setDossierId($dossier->getId())
              ->setNumeroDossier($dossier->getNumero());
        $this->em->persist($notif);
    }
    $this->em->flush();

    return $this->json(['message' => 'Dossier archivé avec succès', 'fusionne' => false]);
}
    // ── GET /api/archivage/archives ─────────────────────────────────────────
    #[Route('/archives', name: 'archives', methods: ['GET'])]
    public function archives(Request $request): JsonResponse
    {
        $statutArchive = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => 'ARCHIVE']);
        if (!$statutArchive) {
            return $this->json(['data' => [], 'total' => 0]);
        }

        $page      = max(1, (int) $request->query->get('page', 1));
        $size      = min(100, (int) $request->query->get('size', 20));
        $numero    = $request->query->get('numero');
        $serviceId = $request->query->get('serviceId');
        $dateDebut = $request->query->get('dateDebut');
        $dateFin   = $request->query->get('dateFin');

        $qb = $this->em->getRepository(Dossier::class)->createQueryBuilder('d')
            ->leftJoin('d.service', 'sv')->addSelect('sv')
            ->where('d.statut = :s')
            ->setParameter('s', $statutArchive);

        if ($numero) {
            $qb->andWhere('d.numero LIKE :n')->setParameter('n', "%$numero%");
        }
        if ($serviceId) {
            $qb->andWhere('sv.id = :sid')->setParameter('sid', (int) $serviceId);
        }
        if ($dateDebut) {
            $qb->andWhere('d.dateArchivage >= :dd')
               ->setParameter('dd', new \DateTimeImmutable($dateDebut));
        }
        if ($dateFin) {
            $qb->andWhere('d.dateArchivage <= :df')
               ->setParameter('df', new \DateTimeImmutable($dateFin . ' 23:59:59'));
        }

        $total    = (int) (clone $qb)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        $dossiers = $qb->orderBy('d.dateArchivage', 'DESC')
            ->setFirstResult(($page - 1) * $size)->setMaxResults($size)
            ->getQuery()->getResult();

        return $this->json([
            'data'  => array_map(function ($d) {
                $nbDocs = (int) $this->em->createQuery(
                    'SELECT COUNT(v.id) FROM App\Entity\VersionDocument v WHERE v.dossier = :d'
                )->setParameter('d', $d)->getSingleScalarResult();

                return [
                    'id'            => $d->getId(),
                    'numero'        => $d->getNumero(),
                    'titre'         => $d->getTitre(),
                    'citoyen'       => $d->getNomCitoyen(),
                    'dateArchivage' => $d->getDateArchivage()?->format('c') ?? $d->getDateMiseAJourStatut()->format('c'),
                    'service'       => $d->getService()->getNom(),
                    'nbDocuments'   => $nbDocs,
                    'description'   => $d->getDescription() ?? '',
                ];
            }, $dossiers),
            'total' => $total,
        ]);
    }
}
