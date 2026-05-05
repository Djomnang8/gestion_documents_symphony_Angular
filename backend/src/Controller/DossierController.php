<?php
namespace App\Controller;

use App\Entity\Dossier;
use App\Entity\HistoriqueStatut;
use App\Entity\Journal;
use App\Entity\Notification;
use App\Entity\Service;
use App\Entity\StatutDossier;
use App\Entity\Utilisateur;
use App\Entity\VersionDocument;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/dossiers', name: 'dossier_')]
class DossierController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private EmailService $emailService
    ) {}

    private function slugifyEmail(string $email): string
{
    return str_replace(['@', '.'], ['_', '_'], $email);
}

    // ── HELPER : notification aux agents du service ────────────────────────
    private function notifierAgentsService(Dossier $dossier, string $titre, string $message, string $type): void
    {
        $agents = $this->em->getRepository(Utilisateur::class)
            ->createQueryBuilder('u')
            ->where('u.service = :service')
            ->andWhere('u.estActif = true')
            ->andWhere('u.estListeNoire = false')
            ->andWhere('u.estSupprime = false')
            ->setParameter('service', $dossier->getService())
            ->getQuery()->getResult();

        foreach ($agents as $agent) {
            $notif = new Notification();
            $notif->setUtilisateur($agent)
                  ->setTitre($titre)
                  ->setMessage($message)
                  ->setType($type)
                  ->setDossierId($dossier->getId())
                  ->setNumeroDossier($dossier->getNumero());
            $this->em->persist($notif);
        }
        $this->em->flush();
    }

    // ── HELPER : journaliser une action ────────────────────────────────────
    private function journaliser(string $module, string $action, string $details): void
    {
        $user = $this->security->getUser();
        $j    = new Journal();
        $j->setModule($module)->setAction($action)->setDetails($details)->setNiveauId(1);
        if ($user instanceof Utilisateur) {
            $j->setUtilisateur($user);
        }
        $this->em->persist($j);
        $this->em->flush();
    }

    // ── LISTE (filtrée par service de l'agent) ─────────────────────────────
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page      = max(1, (int) $request->query->get('page', 1));
        $taille    = min(100, (int) $request->query->get('taille', 10));
        $statut    = $request->query->get('statut');
        $recherche = $request->query->get('recherche');
        $serviceId = $request->query->get('serviceId');
        $dateDebut = $request->query->get('dateDebut');
        $dateFin   = $request->query->get('dateFin');

        $qb = $this->em->getRepository(Dossier::class)->createQueryBuilder('d')
            ->leftJoin('d.statut', 's')->addSelect('s')
            ->leftJoin('d.service', 'sv')->addSelect('sv')
            ->leftJoin('d.agent', 'a')->addSelect('a');

        // Exclure les archives de la liste agent
        $qb->andWhere("s.code != 'ARCHIVE'");

        // Agents voient uniquement les dossiers de leur service
        $user = $this->security->getUser();
        if ($user instanceof Utilisateur && $user->getRoleNom() === 'Agent') {
            if ($user->getService()) {
                $qb->andWhere('d.service = :service')
                   ->setParameter('service', $user->getService());
            }
        } elseif ($serviceId) {
            $qb->andWhere('d.service = :service')
               ->setParameter('service', (int) $serviceId);
        }

        if ($statut) {
            $qb->andWhere('s.code = :statut')->setParameter('statut', $statut);
        }
        if ($recherche) {
            $qb->andWhere('d.numero LIKE :r OR d.nomCitoyen LIKE :r OR d.titre LIKE :r')
               ->setParameter('r', "%$recherche%");
        }
        if ($dateDebut) {
            $qb->andWhere('d.dateDepot >= :dd')
               ->setParameter('dd', new \DateTimeImmutable($dateDebut));
        }
        if ($dateFin) {
            $qb->andWhere('d.dateDepot <= :df')
               ->setParameter('df', new \DateTimeImmutable($dateFin . ' 23:59:59'));
        }

        $qb->orderBy('d.dateDepot', 'DESC');

        $total    = (int) (clone $qb)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        $dossiers = $qb->setFirstResult(($page - 1) * $taille)
                       ->setMaxResults($taille)
                       ->getQuery()->getResult();

        return $this->json([
            'total'    => $total,
            'page'     => $page,
            'taille'   => $taille,
            'dossiers' => array_map([$this, 'serializeListe'], $dossiers),
        ]);
    }

    // ── STATISTIQUES AGENT ─────────────────────────────────────────────────
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $repo = $this->em->getRepository(Dossier::class);
        $user = $this->security->getUser();

        $serviceFilter = null;
        if ($user instanceof Utilisateur && $user->getRoleNom() === 'Agent' && $user->getService()) {
            $serviceFilter = $user->getService();
        }

        $addFilter = function ($qb) use ($serviceFilter) {
            if ($serviceFilter) {
                $qb->andWhere('d.service = :service')->setParameter('service', $serviceFilter);
            }
            return $qb;
        };

        $aujourd    = new \DateTimeImmutable('today');
        $debutSem   = new \DateTimeImmutable('-7 days');
        $seuilRetard = new \DateTimeImmutable('-7 days');

        $qbAuj = $addFilter($repo->createQueryBuilder('d')->select('COUNT(d.id)'));
        $recusAujourdhui = (int) $qbAuj
            ->andWhere('d.dateDepot >= :debut')->setParameter('debut', $aujourd)
            ->getQuery()->getSingleScalarResult();

        $statutEC = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => 'EN_COURS']);
        $qbEC = $addFilter($repo->createQueryBuilder('d')->select('COUNT(d.id)'));
        $enCours = $statutEC ? (int) $qbEC
            ->andWhere('d.statut = :s')->setParameter('s', $statutEC)
            ->getQuery()->getSingleScalarResult() : 0;

        $qbTraites = $addFilter($repo->createQueryBuilder('d')->select('COUNT(d.id)')->leftJoin('d.statut', 's'));
        $traitesSemine = (int) $qbTraites
            ->andWhere('d.dateMiseAJourStatut >= :debut')
            ->andWhere("s.code IN ('TERMINE', 'ARCHIVE', 'REJETE')")
            ->setParameter('debut', $debutSem)
            ->getQuery()->getSingleScalarResult();

        $qbRetard = $addFilter($repo->createQueryBuilder('d')->select('COUNT(d.id)')->leftJoin('d.statut', 'sr'));
        $enRetard = (int) $qbRetard
            ->andWhere('d.dateMiseAJourStatut <= :seuil')
            ->andWhere("sr.code IN ('RECU', 'EN_COURS')")
            ->setParameter('seuil', $seuilRetard)
            ->getQuery()->getSingleScalarResult();

        $statuts   = $this->em->getRepository(StatutDossier::class)->findAll();
        $parStatut = [];
        foreach ($statuts as $s) {
            $qbStat = $addFilter($repo->createQueryBuilder('d')->select('COUNT(d.id)'));
            $parStatut[$s->getCode()] = (int) $qbStat
                ->andWhere('d.statut = :st')->setParameter('st', $s)
                ->getQuery()->getSingleScalarResult();
        }

        $qbTotal = $addFilter($repo->createQueryBuilder('d')->select('COUNT(d.id)'));
        $total   = (int) $qbTotal->getQuery()->getSingleScalarResult();

        return $this->json([
            'total'           => $total,
            'recusAujourdhui' => $recusAujourdhui,
            'enCours'         => $enCours,
            'traitesSemine'   => $traitesSemine,
            'enRetard'        => $enRetard,
            'recu'            => $parStatut['RECU']     ?? 0,
            'transfere'       => $parStatut['TRANSFERE'] ?? 0,
            'rejete'          => $parStatut['REJETE']   ?? 0,
            'termine'         => $parStatut['TERMINE']  ?? 0,
            'archive'         => $parStatut['ARCHIVE']  ?? 0,
            'parStatut'       => $parStatut,
        ]);
    }

    // ── EN RETARD ──────────────────────────────────────────────────────────
    #[Route('/en-retard', name: 'en_retard', methods: ['GET'])]
    public function enRetard(): JsonResponse
    {
        $seuil = new \DateTimeImmutable('-1 day');
        $user  = $this->security->getUser();

        $qb = $this->em->getRepository(Dossier::class)->createQueryBuilder('d')
            ->leftJoin('d.statut', 's')->addSelect('s')
            ->where('d.dateDepot <= :seuil')
            ->andWhere("s.code IN ('RECU', 'EN_COURS')")
            ->setParameter('seuil', $seuil)
            ->orderBy('d.dateDepot', 'ASC');

        if ($user instanceof Utilisateur && $user->getRoleNom() === 'Agent' && $user->getService()) {
            $qb->andWhere('d.service = :service')->setParameter('service', $user->getService());
        }

        $now      = new \DateTimeImmutable();
        $dossiers = $qb->getQuery()->getResult();

        return $this->json(array_map(fn($d) => [
            'id'                  => $d->getId(),
            'numero'              => $d->getNumero(),
            'titre'               => $d->getTitre(),
            'nomCitoyen'          => $d->getNomCitoyen(),
            'statutCode'          => $d->getStatut()->getCode(),
            'statutLibelle'       => $d->getStatut()->getLibelle(),
            'dateDepot'           => $d->getDateDepot()->format('c'),
            'dateMiseAJourStatut' => $d->getDateMiseAJourStatut()->format('c'),
            'joursEnRetard'       => (int) $now->diff($d->getDateDepot())->days,
        ], $dossiers));
    }

    // ── EXPORT CSV ─────────────────────────────────────────────────────────
    #[Route('/export-csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): Response
    {
        $user  = $this->security->getUser();
        $qb    = $this->em->getRepository(Dossier::class)->createQueryBuilder('d')
            ->leftJoin('d.statut', 's')->addSelect('s')
            ->leftJoin('d.service', 'sv')->addSelect('sv')
            ->orderBy('d.dateDepot', 'DESC');

        if ($user instanceof Utilisateur && $user->getRoleNom() === 'Agent' && $user->getService()) {
            $qb->andWhere('d.service = :service')->setParameter('service', $user->getService());
        }

        if ($statut    = $request->query->get('statut'))    $qb->andWhere('s.code = :s')->setParameter('s', $statut);
        if ($recherche = $request->query->get('recherche'))  $qb->andWhere('d.numero LIKE :r OR d.nomCitoyen LIKE :r')->setParameter('r', "%$recherche%");
        if ($sId       = $request->query->get('serviceId'))  $qb->andWhere('d.service = :sid')->setParameter('sid', (int) $sId);
        if ($dd        = $request->query->get('dateDebut'))  $qb->andWhere('d.dateDepot >= :dd')->setParameter('dd', new \DateTimeImmutable($dd));
        if ($df        = $request->query->get('dateFin'))    $qb->andWhere('d.dateDepot <= :df')->setParameter('df', new \DateTimeImmutable($df . ' 23:59:59'));

        $rows = ["\xEF\xBB\xBF\"Numéro\",\"Titre\",\"Citoyen\",\"Email\",\"Téléphone\",\"Statut\",\"Date Dépôt\",\"Dernière MAJ\""];
        foreach ($qb->getQuery()->getResult() as $d) {
            $rows[] = sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s"',
                $d->getNumero(),
                str_replace('"', '""', $d->getTitre()),
                str_replace('"', '""', $d->getNomCitoyen()),
                $d->getEmailCitoyen() ?? '',
                $d->getTelephoneCitoyen() ?? '',
                $d->getStatut()->getCode(),
                $d->getDateDepot()->format('Y-m-d'),
                $d->getDateMiseAJourStatut()->format('Y-m-d')
            );
        }

        $filename = 'dossiers_' . date('Ymd') . '.csv';
        $response = new Response(implode("\n", $rows));
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"$filename\"");
        return $response;
    }

    // ── ARCHIVES ───────────────────────────────────────────────────────────
    #[Route('/archives', name: 'archives', methods: ['GET'])]
    public function archives(Request $request): JsonResponse
    {
        $statutArchive = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => 'ARCHIVE']);
        if (!$statutArchive) {
            return $this->json(['total' => 0, 'page' => 1, 'size' => 12, 'data' => []]);
        }

        $page      = max(1, (int) $request->query->get('page', 1));
        $size      = min(100, (int) $request->query->get('size', 12));
        $numero    = $request->query->get('numero');
        $dateDebut = $request->query->get('dateDebut');
        $dateFin   = $request->query->get('dateFin');

        $qb = $this->em->getRepository(Dossier::class)->createQueryBuilder('d')
            ->leftJoin('d.service', 'sv')->addSelect('sv')
            ->where('d.statut = :s')->setParameter('s', $statutArchive);

        if ($numero) {
            $qb->andWhere('d.numero LIKE :n OR d.nomCitoyen LIKE :n')->setParameter('n', "%$numero%");
        }
        if ($dateDebut) {
            $qb->andWhere('d.dateArchivage >= :dd')->setParameter('dd', new \DateTimeImmutable($dateDebut));
        }
        if ($dateFin) {
            $qb->andWhere('d.dateArchivage <= :df')->setParameter('df', new \DateTimeImmutable($dateFin . ' 23:59:59'));
        }

        $total    = (int) (clone $qb)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        $dossiers = $qb->orderBy('d.dateArchivage', 'DESC')
            ->setFirstResult(($page - 1) * $size)->setMaxResults($size)
            ->getQuery()->getResult();

        return $this->json([
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'data'  => array_map(fn($d) => [
                'id'            => $d->getId(),
                'numero'        => $d->getNumero(),
                'titre'         => $d->getTitre(),
                'citoyen'       => $d->getNomCitoyen(),
                'emailCitoyen'  => $d->getEmailCitoyen() ?? '',
                'service'       => $d->getService()->getNom(),
                'dateArchivage' => $d->getDateArchivage()?->format('c') ?? $d->getDateMiseAJourStatut()->format('c'),
                'nbDocuments'   => $d->getVersionsDocument()->count(),
                'miniature'     => null,
            ], $dossiers),
        ]);
    }

    // ── SUIVI CITOYEN (public) ─────────────────────────────────────────────
    // Route identique à C# : GET /api/dossiers/suivi/{numero}
    #[Route('/suivi/{numero}', name: 'suivi', methods: ['GET'])]
    public function suivi(string $numero): JsonResponse
    {
        $dossier = $this->em->getRepository(Dossier::class)->findOneBy(['numero' => $numero]);
        if (!$dossier) {
            return $this->json(['message' => "Aucun dossier trouvé avec le numéro $numero."], 404);
        }

        return $this->json([
            'id'                  => $dossier->getId(),
            'numero'              => $dossier->getNumero(),
            'titre'               => $dossier->getTitre(),
            'description'         => $dossier->getDescription(),
            'nomCitoyen'          => $dossier->getNomCitoyen(),
            'service'             => $dossier->getService()->getNom(),
            'statutCode'          => $dossier->getStatut()->getCode(),
            'statutLibelle'       => $dossier->getStatut()->getLibelle(),
            'dateDepot'           => $dossier->getDateDepot()->format('c'),
            'dateMiseAJourStatut' => $dossier->getDateMiseAJourStatut()->format('c'),
            'motifRejet'          => $dossier->getMotifRejet(),
            'historique'          => $dossier->getHistoriqueStatuts()->map(fn($h) => [
                'ancienStatut'   => $h->getAncienStatut()?->getLibelle() ?? 'Création',
                'nouveauStatut'  => $h->getNouveauStatut()->getLibelle(),
                'commentaire'    => $h->getCommentaire(),
                'date'           => $h->getDateChangement()->format('c'),
            ])->toArray(),
        ]);
    }

    // ── DÉTAIL ─────────────────────────────────────────────────────────────
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $dossier = $this->em->getRepository(Dossier::class)->find($id);
        if (!$dossier) {
            return $this->json(['message' => 'Dossier non trouvé'], 404);
        }
        return $this->json($this->serializeDetail($dossier));
    }

    // ── CRÉER ──────────────────────────────────────────────────────────────
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data    = json_decode($request->getContent(), true);
        $service = $this->em->getRepository(Service::class)->find($data['serviceId'] ?? 0);
        if (!$service) {
            return $this->json(['message' => 'Service invalide'], 400);
        }

        $statut = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => 'RECU']);
        if (!$statut) {
            return $this->json(['message' => 'Statut initial introuvable'], 500);
        }

        $dossier = new Dossier();
        $dossier->setTitre($data['titre'] ?? '')
                ->setDescription($data['description'] ?? null)
                ->setNomCitoyen($data['nomCitoyen'] ?? '')
                ->setEmailCitoyen($data['emailCitoyen'] ?? null)
                ->setTelephoneCitoyen($data['telephoneCitoyen'] ?? null)
                ->setService($service)
                ->setStatut($statut)
                ->setNumero($this->genererNumero());

        $user = $this->security->getUser();
        if ($user instanceof Utilisateur) {
            $dossier->setAgent($user);
        }

        $this->em->persist($dossier);
        $this->em->flush();
        $this->journaliser('DOSSIERS', 'CREATION', "Création dossier : {$dossier->getNumero()}");

        return $this->json(['id' => $dossier->getId(), 'numero' => $dossier->getNumero()], 201);
    }

    // ── CHANGER STATUT (avec email rejet + notification agents) ────────────
    #[Route('/{id}/statut', name: 'changer_statut', methods: ['PUT', 'PATCH'])]
    public function changerStatut(string $id, Request $request): JsonResponse
    {
        $dossier = $this->em->getRepository(Dossier::class)->find($id);
        if (!$dossier) {
            return $this->json(['message' => 'Dossier non trouvé'], 404);
        }

        $data          = json_decode($request->getContent(), true);
        $code          = $data['nouveauStatutCode'] ?? $data['statut'] ?? null;
        $nouveauStatut = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => $code]);

        if (!$nouveauStatut) {
            return $this->json(['message' => 'Statut invalide'], 400);
        }

        if ($code === 'REJETE' && empty($data['commentaire'])) {
            return $this->json(['error' => 'Un motif de rejet est obligatoire.'], 400);
        }

        $historique = new HistoriqueStatut();
        $historique->setDossier($dossier)
                   ->setAncienStatut($dossier->getStatut())
                   ->setNouveauStatut($nouveauStatut)
                   ->setCommentaire($data['commentaire'] ?? null)
                   ->setAgent($this->security->getUser() instanceof Utilisateur ? $this->security->getUser() : null);

        if ($code === 'REJETE') {
            $dossier->setMotifRejet($data['commentaire'] ?? null);
        }

        $dossier->setStatut($nouveauStatut)
                ->setDateMiseAJourStatut(new \DateTimeImmutable());

        $this->em->persist($historique);
        $this->em->flush();
        $this->journaliser('DOSSIERS', 'CHANGEMENT_STATUT', "Statut changé en {$nouveauStatut->getCode()} – {$dossier->getNumero()}");

        // Email au citoyen
        if ($dossier->getEmailCitoyen()) {
            try {
                if ($code === 'REJETE') {
                    $this->emailService->envoyerNotificationRejet(
                        $dossier->getEmailCitoyen(),
                        $dossier->getNomCitoyen(),
                        $dossier->getNumero(),
                        $dossier->getTitre(),
                        $data['commentaire'] ?? $dossier->getMotifRejet()
                    );
                } else {
                    $this->emailService->envoyerNotificationStatut(
                        $dossier->getEmailCitoyen(),
                        $dossier->getNomCitoyen(),
                        $dossier->getNumero(),
                        $nouveauStatut->getLibelle(),
                        $data['commentaire'] ?? null
                    );
                }
            } catch (\Exception $e) {
                // Ne pas bloquer si l'email échoue
            }
        }

        // Notification aux agents du service
        $this->notifierAgentsService(
            $dossier,
            "Changement de statut - {$dossier->getNumero()}",
            "Le dossier {$dossier->getNumero()} est passé à {$nouveauStatut->getLibelle()}.",
            'STATUT'
        );

        return $this->json(['message' => 'Statut mis à jour', 'statut' => $nouveauStatut->getLibelle()]);
    }

    // ── TRANSFÉRER ─────────────────────────────────────────────────────────
    #[Route('/{id}/transferer', name: 'transferer', methods: ['PUT', 'POST', 'PATCH'])]
    public function transferer(string $id, Request $request): JsonResponse
    {
        $dossier = $this->em->getRepository(Dossier::class)->find($id);
        if (!$dossier) {
            return $this->json(['message' => 'Dossier non trouvé'], 404);
        }

        $data    = json_decode($request->getContent(), true);
        $service = $this->em->getRepository(Service::class)->find($data['serviceId'] ?? 0);
        if (!$service || !$service->isEstActif()) {
            return $this->json(['message' => 'Service destination invalide ou inactif'], 400);
        }

        $statutTransfere = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => 'TRANSFERE']);

        $historique = new HistoriqueStatut();
        $historique->setDossier($dossier)
                   ->setAncienStatut($dossier->getStatut())
                   ->setNouveauStatut($statutTransfere)
                   ->setCommentaire(
                       'Transféré vers le service ' . $service->getNom() .
                       (empty($data['commentaire']) ? '' : ' : ' . $data['commentaire'])
                   )
                   ->setAgent($this->security->getUser() instanceof Utilisateur ? $this->security->getUser() : null);

        $dossier->setStatut($statutTransfere)
                ->setService($service)
                ->setDateMiseAJourStatut(new \DateTimeImmutable());

        $this->em->persist($historique);
        $this->em->flush();
        $this->journaliser('DOSSIERS', 'TRANSFERT',
    "Transféré vers {$service->getNom()} – {$dossier->getNumero()}");

        $this->notifierAgentsService(
            $dossier,
            "Dossier transféré - {$dossier->getNumero()}",
            "Le dossier {$dossier->getNumero()} a été transféré vers le service {$service->getNom()}.",
            'STATUT'
        );

        return $this->json(['message' => 'Dossier transféré avec succès.']);
    }

    // ── ARCHIVER (POST sur un dossier spécifique) ──────────────────────────
    #[Route('/{id}/archiver', name: 'archiver', methods: ['PUT', 'POST'])]
    public function archiver(string $id): JsonResponse
    {
        $dossier = $this->em->getRepository(Dossier::class)->find($id);
        if (!$dossier) {
            return $this->json(['message' => 'Dossier non trouvé'], 404);
        }

        $statutArchive = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => 'ARCHIVE']);

        $historique = new HistoriqueStatut();
        $historique->setDossier($dossier)
                   ->setAncienStatut($dossier->getStatut())
                   ->setNouveauStatut($statutArchive)
                   ->setCommentaire('Archivage définitif')
                   ->setAgent($this->security->getUser() instanceof Utilisateur ? $this->security->getUser() : null);

        $dossier->setStatut($statutArchive)
                ->setDateArchivage(new \DateTimeImmutable())
                ->setDateMiseAJourStatut(new \DateTimeImmutable());

        $this->em->persist($historique);
        $this->em->flush();
        $this->journaliser('DOSSIERS', 'ARCHIVAGE', "Archivage dossier : {$dossier->getNumero()}");

        return $this->json(['message' => 'Dossier archivé avec succès']);
    }

    // ── UPLOAD DOCUMENT ────────────────────────────────────────────────────
    #[Route('/{id}/upload', name: 'upload', methods: ['POST'])]
    public function upload(string $id, Request $request): JsonResponse
    {
        $dossier = $this->em->getRepository(Dossier::class)->find($id);
        if (!$dossier) {
            return $this->json(['message' => 'Dossier non trouvé'], 404);
        }

        $fichier = $request->files->get('fichier');
        if (!$fichier) {
            return $this->json(['message' => 'Aucun fichier reçu'], 400);
        }


$citoyenEmail = $dossier->getEmailCitoyen();
$citoyenNom   = $dossier->getNomCitoyen();
if ($citoyenEmail) {
    $dossierDir = $this->getCitizenFolder($citoyenNom, $citoyenEmail);
} else {
    $dossierDir = $id; // fallback UUID
}
$uploadDir = $this->getParameter('kernel.project_dir')
    . '/public/uploads/citoyens/' . $dossierDir;


        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // Capturer les métadonnées AVANT move()
        $nomOriginal = $fichier->getClientOriginalName();
        $mimeType    = $fichier->getClientMimeType();
        $taille      = $fichier->getSize();
        $ext         = $fichier->getClientOriginalExtension() ?: 'bin';
        $nomUnique   = uniqid('doc_', true) . '.' . $ext;

        $fichier->move($uploadDir, $nomUnique);

        // Désactiver les versions précédentes
        foreach ($dossier->getVersionsDocument() as $v) {
            $v->setEstActive(false);
        }

        $derniere = $this->em->getRepository(VersionDocument::class)
            ->createQueryBuilder('v')
            ->where('v.dossier = :d')->setParameter('d', $dossier)
            ->orderBy('v.numeroVersion', 'DESC')->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        $numeroVersion = $derniere ? $derniere->getNumeroVersion() + 1 : 1;

        $version = new VersionDocument();
        $version->setDossier($dossier)
                ->setNomFichier($nomOriginal)
                ->setCheminFichier('/uploads/citoyens/' . $dossierDir . '/' . $nomUnique)
                ->setTypeFichier($mimeType)
                ->setTailleFichier($taille)
                ->setNumeroVersion($numeroVersion)
                ->setEstActive(true)
                ->setUtilisateur($this->security->getUser() instanceof Utilisateur ? $this->security->getUser() : null);

        $this->em->persist($version);
        $this->em->flush();

        return $this->json(['message' => 'Document uploadé', 'version' => $numeroVersion], 201);
    }

    // ── DÉPÔT PUBLIC CITOYEN ───────────────────────────────────────────────
    #[Route('/public/depot', name: 'public_depot', methods: ['POST'])]
    public function publicDepot(Request $request): JsonResponse
    {
        $serviceId = $request->request->get('serviceId');
        $service   = $this->em->getRepository(Service::class)->find($serviceId);
        if (!$service) {
            return $this->json(['message' => "Service invalide. Vérifiez que l'ID service existe."], 400);
        }

        $statut = $this->em->getRepository(StatutDossier::class)->findOneBy(['code' => 'RECU']);
        if (!$statut) {
            return $this->json(['message' => 'Statut initial introuvable.'], 500);
        }

        $dossier = new Dossier();
        $dossier->setTitre($request->request->get('titre', ''))
                ->setDescription($request->request->get('description'))
                ->setNomCitoyen($request->request->get('nomCitoyen', ''))
                ->setEmailCitoyen($request->request->get('emailCitoyen'))
                ->setTelephoneCitoyen($request->request->get('telephoneCitoyen'))
                ->setService($service)
                ->setStatut($statut)
                ->setNumero($this->genererNumero());

        $this->em->persist($dossier);
        $this->em->flush();
        $this->journaliser('DOSSIERS', 'DEPOT', "Dépôt public : {$dossier->getNumero()}");

        // Traitement des fichiers joints
        $fichiers = $request->files->get('fichiers') ?? [];
        if (!is_array($fichiers)) {
            $fichiers = array_filter([$fichiers]);
        }

        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $maxSize    = 10 * 1024 * 1024; // 10 Mo
        $maxFiles   = 4;

        if (count($fichiers) > $maxFiles) {
            return $this->json(['message' => "Maximum $maxFiles fichiers autorisés."], 400);
        }

            if (!empty($fichiers)) {
    $citoyenEmail = $request->request->get('emailCitoyen');
    $citoyenNom   = $request->request->get('nomCitoyen', 'Inconnu');
    if ($citoyenEmail) {
        $dossierDir = $this->getCitizenFolder($citoyenNom, $citoyenEmail);
        $uploadDir  = $this->getParameter('kernel.project_dir')
            . '/public/uploads/citoyens/' . $dossierDir;
    } else {
        $dossierDir = $dossier->getId();
        $uploadDir  = $this->getParameter('kernel.project_dir')
            . '/public/uploads/citoyens/' . $dossierDir;
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }



            $numVersion = 1;
            foreach ($fichiers as $fichier) {
                if (!$fichier) continue;

                $ext = strtolower($fichier->getClientOriginalExtension() ?: 'bin');
                if (!in_array($ext, $allowedExt)) continue;
                if ($fichier->getSize() > $maxSize) continue;

                $nomOriginal = $fichier->getClientOriginalName();
                $mimeType    = $fichier->getClientMimeType();
                $taille      = $fichier->getSize();
                $nomUnique   = uniqid('doc_', true) . "_$numVersion.$ext";

                $fichier->move($uploadDir, $nomUnique);

                $version = new VersionDocument();
                $version->setDossier($dossier)
                        ->setNomFichier($nomOriginal)
                        ->setCheminFichier('/uploads/citoyens/' . $dossierDir . '/' . $nomUnique)
                        ->setTypeFichier($mimeType)
                        ->setTailleFichier($taille)
                        ->setNumeroVersion($numVersion++)
                        ->setEstActive(true);

                $this->em->persist($version);

            }
            $this->em->flush();
        }

        // Email de confirmation au citoyen
        $emailCitoyen = $request->request->get('emailCitoyen');
        if ($emailCitoyen) {
            try {
                $this->emailService->envoyerConfirmationDepot(
                    $emailCitoyen,
                    $request->request->get('nomCitoyen', ''),
                    $dossier->getNumero(),
                    $dossier->getTitre()
                );
            } catch (\Exception $e) {
                // Ne pas bloquer si email échoue
            }
        }

        // Notifier tous les administrateurs (ou utilisateurs avec rôle Admin)
$admins = $this->em->getRepository(Utilisateur::class)
    ->createQueryBuilder('u')
    ->where('u.typeUtilisateur = :type')
    ->andWhere('u.estActif = true')
    ->setParameter('type', 'Administrateur')
    ->getQuery()->getResult();

foreach ($admins as $admin) {
    $notif = new Notification();
    $notif->setUtilisateur($admin)
          ->setTitre("Nouveau dossier citoyen")
          ->setMessage("Le dossier {$dossier->getNumero()} a été déposé par {$dossier->getNomCitoyen()}.")
          ->setType('INFO')
          ->setDossierId($dossier->getId())
          ->setNumeroDossier($dossier->getNumero());
    $this->em->persist($notif);
}
$this->em->flush();

        return $this->json([
            'numeroDossier' => $dossier->getNumero(),
            'message'       => 'Dossier déposé avec succès.',
        ], 201);
    }

    // ── TÉLÉCHARGER FICHIER ────────────────────────────────────────────────
    #[Route('/fichiers/download', name: 'fichier_download', methods: ['GET'])]
    public function fichierDownload(Request $request): Response
    {
        $chemin   = $request->query->get('chemin', '');
        $fullPath = $this->getParameter('kernel.project_dir') . '/public' . $chemin;

        if (empty($chemin) || !file_exists($fullPath)) {
            return $this->json(['message' => 'Fichier non trouvé : ' . $chemin], 404);
        }

        $uploadsDir = realpath($this->getParameter('kernel.project_dir') . '/public/uploads');
        $realPath   = realpath($fullPath);
        if (!$realPath || !str_starts_with($realPath, $uploadsDir)) {
            return $this->json(['message' => 'Accès refusé'], 403);
        }

        return new BinaryFileResponse($realPath, 200, [], true, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    private function getCitizenFolder(string $nomCitoyen, string $emailCitoyen): string
{
    // Exemple : DJOMNANG_EMMANUELLA_JOYCE_joycedjomnang_gmail_com
    $safeName  = preg_replace('/[^a-zA-Z0-9]/', '_', $nomCitoyen);
    $safeEmail = str_replace(['@', '.'], ['_', '_'], $emailCitoyen);
    return $safeName . '_' . $safeEmail;
}

    // ── HELPERS ────────────────────────────────────────────────────────────
    private function genererNumero(): string
    {
        $annee = date('Y');
        $count = (int) $this->em->getRepository(Dossier::class)
            ->createQueryBuilder('d')->select('COUNT(d.id)')
            ->getQuery()->getSingleScalarResult();
        return sprintf('DOS-%s-%05d', $annee, $count + 1);
    }

    private function serializeListe(Dossier $d): array
    {
        return [
            'id'                  => $d->getId(),
            'numero'              => $d->getNumero(),
            'titre'               => $d->getTitre(),
            'nomCitoyen'          => $d->getNomCitoyen(),
            'emailCitoyen'        => $d->getEmailCitoyen(),
            'telephoneCitoyen'    => $d->getTelephoneCitoyen(),
            'statutCode'          => $d->getStatut()->getCode(),
            'statutLibelle'       => $d->getStatut()->getLibelle(),
            'serviceNom'          => $d->getService()->getNom(),
            'dateDepot'           => $d->getDateDepot()->format('c'),
            'dateMiseAJourStatut' => $d->getDateMiseAJourStatut()->format('c'),
        ];
    }

    private function serializeDetail(Dossier $d): array
    {
        $docs = $d->getVersionsDocument()->map(fn($v) => [
            'id'            => $v->getId(),
            'nomFichier'    => $v->getNomFichier(),
            'cheminFichier' => $v->getCheminFichier(),
            'typeFichier'   => $v->getTypeFichier(),
            'tailleFichier' => $v->getTailleFichier(),
            'numeroVersion' => $v->getNumeroVersion(),
            'dateCreation'  => $v->getDateCreation()->format('c'),
            'estActive'     => $v->isEstActive(),
        ])->toArray();

        $historique = $d->getHistoriqueStatuts()->map(fn($h) => [
            'ancienStatut'   => $h->getAncienStatut()?->getCode() ?? '—',
            'nouveauStatut'  => $h->getNouveauStatut()->getCode(),
            'commentaire'    => $h->getCommentaire(),
            'dateChangement' => $h->getDateChangement()->format('c'),
            'agentNom'       => $h->getAgent()
                ? $h->getAgent()->getPrenom() . ' ' . $h->getAgent()->getNom()
                : 'Système',
        ])->toArray();

        return [
            'id'                  => $d->getId(),
            'numero'              => $d->getNumero(),
            'titre'               => $d->getTitre(),
            'description'         => $d->getDescription(),
            'nomCitoyen'          => $d->getNomCitoyen(),
            'emailCitoyen'        => $d->getEmailCitoyen(),
            'telephoneCitoyen'    => $d->getTelephoneCitoyen(),
            'motifRejet'          => $d->getMotifRejet(),
            'statutCode'          => $d->getStatut()->getCode(),
            'statutLibelle'       => $d->getStatut()->getLibelle(),
            'serviceId'           => $d->getService()->getId(),
            'serviceNom'          => $d->getService()->getNom(),
            'dateDepot'           => $d->getDateDepot()->format('c'),
            'dateMiseAJourStatut' => $d->getDateMiseAJourStatut()->format('c'),
            'dateArchivage'       => $d->getDateArchivage()?->format('c'),
            'documents'           => $docs,
            'historique'          => $historique,
        ];
    }
}
