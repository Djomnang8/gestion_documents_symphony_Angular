<?php
namespace App\Controller;

use App\Entity\Dossier;
use App\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;



class PublicController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    // ── Services (accès public citoyen) ───────────────────────────────────
    #[Route('/api/public/services', name: 'public_services', methods: ['GET'])]
    public function services(): JsonResponse
    {
        $services = $this->em->getRepository(Service::class)->findBy(['estActif' => true]);
        return $this->json(array_map(fn($s) => [
            'id'          => $s->getId(),
            'nom'         => $s->getNom(),
            'description' => $s->getDescription(),
        ], $services));
    }

    // ── Services (pour les agents connectés) ──────────────────────────────
    #[Route('/api/services', name: 'services_list', methods: ['GET'])]
    public function servicesList(): JsonResponse
    {
        return $this->services();
    }

    // ── Suivi citoyen (accès public) ──────────────────────────────────────
    #[Route('/api/public/suivi/{numero}', name: 'public_suivi', methods: ['GET'])]
    public function suivi(string $numero): JsonResponse
    {
        $dossier = $this->em->getRepository(Dossier::class)->findOneBy(['numero' => $numero]);
        if (!$dossier) {
            return $this->json(['message' => 'Dossier non trouvé'], 404);
        }

        return $this->json([
            'numero'              => $dossier->getNumero(),
            'titre'               => $dossier->getTitre(),
            'statut'              => $dossier->getStatut()->getCode(),
            'statutLibelle'       => $dossier->getStatut()->getLibelle(),
            'nomCitoyen'          => $dossier->getNomCitoyen(),
            'service'             => $dossier->getService()->getNom(),
            'dateDepot'           => $dossier->getDateDepot()->format('c'),
            'dateMiseAJourStatut' => $dossier->getDateMiseAJourStatut()->format('c'),
            'motifRejet'          => $dossier->getMotifRejet(),
            'historique'          => $dossier->getHistoriqueStatuts()->map(fn($h) => [
                'ancienStatut'   => $h->getAncienStatut()?->getLibelle() ?? 'Création',
                'nouveauStatut'  => $h->getNouveauStatut()->getLibelle(),
                'commentaire'    => $h->getCommentaire(),
                'dateChangement' => $h->getDateChangement()->format('c'),
            ])->toArray(),
        ]);
    }
}
