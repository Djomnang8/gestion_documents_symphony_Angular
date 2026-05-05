<?php
namespace App\Controller;

use App\Entity\VersionDocument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Route dédiée /api/fichiers
 * Équivalent exact du FichiersController C#
 */
class FichierController extends AbstractController
{
    // ── GET /api/fichiers/download?chemin=... ──────────────────────────────
    // Sert le fichier depuis public/uploads (sécurité anti path-traversal)
    #[Route('/api/fichiers/download', name: 'fichier_download_global', methods: ['GET'])]
    public function download(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $chemin   = $request->query->get('chemin', '');
        $fullPath = $this->getParameter('kernel.project_dir') . '/public' . $chemin;

        if (empty($chemin)) {
            return new JsonResponse(['message' => 'Chemin manquant.'], 400);
        }

        // Sécurité path traversal
        $uploadsDir = realpath($this->getParameter('kernel.project_dir') . '/public/uploads');
        $realPath   = realpath($fullPath);

        if (!$realPath) {
            return new JsonResponse(['message' => 'Fichier introuvable.'], 404);
        }
        if (!$uploadsDir || !str_starts_with($realPath, $uploadsDir)) {
            return new JsonResponse(['message' => 'Accès refusé.'], 403);
        }
        if (!file_exists($realPath)) {
            return new JsonResponse(['message' => 'Fichier introuvable.'], 404);
        }

        // Déterminer la disposition (inline ou attachment)
        $ext           = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        $inlineExts    = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $disposition   = in_array($ext, $inlineExts)
            ? ResponseHeaderBag::DISPOSITION_INLINE
            : ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        return new BinaryFileResponse($realPath, 200, [], true, $disposition);
    }

    // ── GET /api/fichiers/preview/{dossierId} ──────────────────────────────
    // Retourne la version active d'un dossier
    #[Route('/api/fichiers/preview/{dossierId}', name: 'fichier_preview', methods: ['GET'])]
    public function preview(string $dossierId, EntityManagerInterface $em): \Symfony\Component\HttpFoundation\Response
    {
        $version = $em->getRepository(VersionDocument::class)
            ->createQueryBuilder('v')
            ->where('v.dossier = :d')
            ->andWhere('v.estActive = true')
            ->setParameter('d', $dossierId)
            ->orderBy('v.numeroVersion', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        if (!$version) {
            return new JsonResponse(['message' => 'Aucun document pour ce dossier.'], 404);
        }

        return $this->download($this->createRequestWithChemin($version->getCheminFichier()));
    }

    // ── Helper : créer un Request factice avec un chemin ─────────────────
    private function createRequestWithChemin(string $chemin): Request
    {
        $req = new Request();
        $req->query->set('chemin', $chemin);
        return $req;
    }
}
