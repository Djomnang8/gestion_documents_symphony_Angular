<?php
namespace App\Controller;

use App\Entity\Dossier;
use App\Entity\Journal;
use App\Entity\Utilisateur;
use App\Entity\VersionDocument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/versions', name: 'version_')]
class VersionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security
    ) {}

    // ── GET /api/versions/{dossierId} ──────────────────────────────────────
    // Retourne toutes les versions triées de la plus récente
    #[Route('/{dossierId}', name: 'list', methods: ['GET'])]
    public function list(string $dossierId): JsonResponse
    {
        $dossier = $this->em->getRepository(Dossier::class)->find($dossierId);
        if (!$dossier) {
            return $this->json(['message' => 'Dossier non trouvé'], 404);
        }

        $versions = $dossier->getVersionsDocument()->toArray();
        usort($versions, fn($a, $b) => $b->getNumeroVersion() - $a->getNumeroVersion());

        return $this->json(array_map(fn($v) => [
            'id'            => $v->getId(),
            'dossierId'     => $dossierId,
            'numero'        => $v->getNumeroVersion(),
            'nomFichier'    => $v->getNomFichier(),
            'tailleFichier' => $v->getTailleFichier() ?? 0,
            'typeFichier'   => $v->getTypeFichier() ?? '',
            'dateCreation'  => $v->getDateCreation()->format('c'),
            'auteur'        => $v->getUtilisateur()
                ? $v->getUtilisateur()->getPrenom() . ' ' . $v->getUtilisateur()->getNom()
                : 'Citoyen',
            'estActive'     => $v->isEstActive(),
            'commentaire'   => $v->getCommentaire() ?? '',
        ], $versions));
    }

    // ── POST /api/versions/{id}/restaurer ─────────────────────────────────
    // Restaure une version : désactive toutes les autres, active celle-ci
    // Condition : la version doit exister ET ne pas être déjà active
    #[Route('/{id}/restaurer', name: 'restaurer', methods: ['POST'])]
    public function restaurer(string $id): JsonResponse
    {
        $version = $this->em->getRepository(VersionDocument::class)->find($id);
        if (!$version) {
            return $this->json(['error' => 'Version introuvable.'], 404);
        }

        if ($version->isEstActive()) {
            return $this->json(['error' => 'Cette version est déjà la version active.'], 400);
        }

        // Désactiver toutes les versions du dossier
        foreach ($version->getDossier()->getVersionsDocument() as $v) {
            $v->setEstActive(false);
        }

        // Activer la version choisie
        $version->setEstActive(true);
        $this->em->flush();

        // Journaliser
        $user = $this->security->getUser();
        $j = new Journal();
        $j->setModule('Archivage')
          ->setAction('RESTAURATION_VERSION')
          ->setDetails(sprintf(
              'Restauré version %d du dossier %s',
              $version->getNumeroVersion(),
              $version->getDossier()->getNumero()
          ))
          ->setNiveauId(1)
          ->setEntiteId($id);

        if ($user instanceof Utilisateur) {
            $j->setUtilisateur($user);
        }

        $this->em->persist($j);
        $this->em->flush();

        return $this->json([
            'message' => sprintf('Version %d restaurée avec succès.', $version->getNumeroVersion()),
        ]);
    }
}
