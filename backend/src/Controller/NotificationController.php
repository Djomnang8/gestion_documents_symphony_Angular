<?php
namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Rappel;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications', name: 'notif_')]
class NotificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security
    ) {}

    // ── GET /api/notifications?onglet=toutes|non-lues|rappels ─────────────
    // Retourne les notifications + compteurs (total, nonLues, rappels)
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $onglet = $request->query->get('onglet', 'toutes');

        $qb = $this->em->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->where('n.utilisateur = :user')
            ->andWhere('n.estSupprimee = false')
            ->setParameter('user', $user)
            ->orderBy('n.dateCreation', 'DESC');

        if ($onglet === 'non-lues') {
            $qb->andWhere('n.estLue = false');
        } elseif ($onglet === 'rappels') {
            $qb->andWhere('n.type = :type')->setParameter('type', 'RAPPEL');
        }

        // Compteurs globaux (sans filtre onglet)
        $total = (int) $this->em->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.utilisateur = :user')
            ->andWhere('n.estSupprimee = false')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();

        $nonLues = (int) $this->em->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.utilisateur = :user')
            ->andWhere('n.estLue = false')
            ->andWhere('n.estSupprimee = false')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();

        $rappels = (int) $this->em->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.utilisateur = :user')
            ->andWhere("n.type = 'RAPPEL'")
            ->andWhere('n.estSupprimee = false')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();

        $notifs = $qb->getQuery()->getResult();

        return $this->json([
            'total'   => $total,
            'nonLues' => $nonLues,
            'rappels' => $rappels,
            'data'    => array_map(fn($n) => [
                'id'            => $n->getId(),
                'titre'         => $n->getTitre(),
                'description'   => $n->getMessage(),
                'type'          => $n->getType(),
                'dossierId'     => $n->getDossierId(),
                'numeroDossier' => $n->getNumeroDossier(),
                'estLue'        => $n->isEstLue(),
                'dateCreation'  => $n->getDateCreation()->format('c'),
            ], $notifs),
        ]);
    }

    // ── PUT /api/notifications/{id}/lue ───────────────────────────────────
    // Compatible aussi avec /lire (ancien nom Symfony) via alias
    #[Route('/{id}/lue', name: 'lue', methods: ['PUT', 'PATCH'])]
    #[Route('/{id}/lire', name: 'lire', methods: ['PUT', 'PATCH'])]
    public function markAsRead(int $id): JsonResponse
    {
        $notif = $this->em->getRepository(Notification::class)->find($id);
        if (!$notif) {
            return $this->json(['message' => 'Non trouvée'], 404);
        }

        $notif->setEstLue(true);
        $this->em->flush();

        return $this->json(null, 200);
    }

    // ── PUT /api/notifications/tout-lire ──────────────────────────────────
    // Compatible aussi avec /lire-toutes (ancien nom Symfony)
    #[Route('/tout-lire', name: 'tout_lire', methods: ['PUT', 'PATCH'])]
    #[Route('/lire-toutes', name: 'lire_toutes', methods: ['PUT', 'PATCH'])]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->security->getUser();
        $notifs = $this->em->getRepository(Notification::class)->findBy([
            'utilisateur' => $user,
            'estLue'      => false,
        ]);
        foreach ($notifs as $n) {
            $n->setEstLue(true);
        }
        $this->em->flush();

        return $this->json(null, 200);
    }

    // ── DELETE /api/notifications/{id} ────────────────────────────────────
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $notif = $this->em->getRepository(Notification::class)->find($id);
        if (!$notif) {
            return $this->json(['message' => 'Non trouvée'], 404);
        }

        $notif->setEstSupprimee(true);
        $this->em->flush();

        return $this->json(null, 204);
    }

    // ── GET /api/notifications/emails ─────────────────────────────────────
    // Historique des rappels/emails envoyés à l'utilisateur connecté
    #[Route('/emails', name: 'emails', methods: ['GET'])]
    public function emails(): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json([], 200);
        }

        // Vérifie si la table Rappel existe, sinon retourne un tableau vide
        try {
            $rappels = $this->em->getRepository(Rappel::class)
                ->createQueryBuilder('r')
                ->where('r.utilisateur = :user')
                ->setParameter('user', $user)
                ->orderBy('r.dateEnvoi', 'DESC')
                ->getQuery()->getResult();

            return $this->json(array_map(fn($r) => [
                'id'           => $r->getId(),
                'destinataire' => $user->getEmail(),
                'objet'        => $r->getObjet() ?? $r->getTitre(),
                'type'         => $r->getType(),
                'statut'       => $r->getStatut(),
                'dateEnvoi'    => $r->getDateEnvoi()->format('c'),
                'tentatives'   => $r->getTentatives(),
                'erreur'       => $r->getErreur(),
            ], $rappels));
        } catch (\Exception $e) {
            return $this->json([]);
        }
    }

    // ── POST /api/notifications/emails/{id}/retry ─────────────────────────
    #[Route('/emails/{id}/retry', name: 'emails_retry', methods: ['POST'])]
    public function retryEmail(int $id): JsonResponse
    {
        try {
            $rappel = $this->em->getRepository(Rappel::class)->find($id);
            if (!$rappel) {
                return $this->json(['message' => 'Email introuvable'], 404);
            }

            $rappel->setStatut('EN_ATTENTE')
                   ->setTentatives(($rappel->getTentatives() ?? 0) + 1);
            $this->em->flush();

            return $this->json(['message' => "Email remis en file d'envoi."]);
        } catch (\Exception $e) {
            return $this->json(['message' => 'Erreur lors de la remise en file.'], 500);
        }
    }
}
