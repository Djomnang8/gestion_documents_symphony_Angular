<?php
namespace App\Controller;

use App\Entity\Journal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/journaux', name: 'journal_')]
class JournalController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security
    ) {}

    // ── GET /api/journaux ─────────────────────────────────────────────────
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(Journal::class)
            ->createQueryBuilder('j')
            ->leftJoin('j.utilisateur', 'u')->addSelect('u')
            ->orderBy('j.dateAction', 'DESC');

        // Filtre par utilisateur (par ID ou par objet connecté)
        if ($uid = $request->query->get('utilisateurId')) {
            $qb->andWhere('j.utilisateur = :uid')->setParameter('uid', (int) $uid);
        }
        if ($module   = $request->query->get('module'))   {
            $qb->andWhere('j.module LIKE :m')->setParameter('m', "%$module%");
        }
        if ($action   = $request->query->get('action'))   {
            $qb->andWhere('j.action LIKE :a')->setParameter('a', "%$action%");
        }
        if ($niveauId = $request->query->get('niveauId')) {
            $qb->andWhere('j.niveauId = :n')->setParameter('n', (int) $niveauId);
        }
        if ($dd = $request->query->get('dateDebut')) {
            $qb->andWhere('j.dateAction >= :dd')->setParameter('dd', new \DateTimeImmutable($dd));
        }
        if ($df = $request->query->get('dateFin')) {
            $qb->andWhere('j.dateAction <= :df')
               ->setParameter('df', new \DateTimeImmutable($df . ' 23:59:59'));
        }

        $page     = max(1, (int) $request->query->get('page', 1));
        $pageSize = min(200, (int) ($request->query->get('pageSize') ?? $request->query->get('limit', 30)));
        $total    = (int) (clone $qb)->select('COUNT(j.id)')->getQuery()->getSingleScalarResult();

        $journaux = $qb->setFirstResult(($page - 1) * $pageSize)
                       ->setMaxResults($pageSize)
                       ->getQuery()->getResult();

        return $this->json([
            'total'    => $total,
            'page'     => $page,
            'pageSize' => $pageSize,
            'data'     => array_map([$this, 'serialize'], $journaux),
        ]);
    }

    // ── GET /api/journaux/mes-activites ───────────────────────────────────
    #[Route('/mes-activites', name: 'mes_activites', methods: ['GET'])]
    public function mesActivites(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        $qb   = $this->em->getRepository(Journal::class)
            ->createQueryBuilder('j')
            ->leftJoin('j.utilisateur', 'u')->addSelect('u')
            ->orderBy('j.dateAction', 'DESC');

        if ($uid = $request->query->get('utilisateurId')) {
            $qb->andWhere('j.utilisateur = :uid')->setParameter('uid', (int) $uid);
        } elseif ($user) {
            $qb->andWhere('j.utilisateur = :user')->setParameter('user', $user);
        }
        if ($dd = $request->query->get('dateDebut')) {
            $qb->andWhere('j.dateAction >= :dd')->setParameter('dd', new \DateTimeImmutable($dd));
        }
        if ($df = $request->query->get('dateFin')) {
            $qb->andWhere('j.dateAction <= :df')
               ->setParameter('df', new \DateTimeImmutable($df . ' 23:59:59'));
        }

        $page     = max(1, (int) $request->query->get('page', 1));
        $pageSize = min(100, (int) ($request->query->get('pageSize') ?? 20));
        $total    = (int) (clone $qb)->select('COUNT(j.id)')->getQuery()->getSingleScalarResult();

        $journaux = $qb->setFirstResult(($page - 1) * $pageSize)
                       ->setMaxResults($pageSize)
                       ->getQuery()->getResult();

        return $this->json([
            'total'    => $total,
            'page'     => $page,
            'pageSize' => $pageSize,
            'data'     => array_map([$this, 'serialize'], $journaux),
        ]);
    }

    // ── GET /api/journaux/modules ─────────────────────────────────────────
    #[Route('/modules', name: 'modules', methods: ['GET'])]
    public function modules(): JsonResponse
    {
        $result = $this->em->getRepository(Journal::class)
            ->createQueryBuilder('j')
            ->select('DISTINCT j.module')
            ->orderBy('j.module', 'ASC')
            ->getQuery()->getResult();

        return $this->json(array_column($result, 'module'));
    }

    // ── GET /api/journaux/export ───────────────────────────────────────────
    // Export CSV identique au C#
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $qb = $this->em->getRepository(Journal::class)
            ->createQueryBuilder('j')
            ->leftJoin('j.utilisateur', 'u')->addSelect('u')
            ->orderBy('j.dateAction', 'DESC');

        if ($uid = $request->query->get('utilisateurId')) {
            $qb->andWhere('j.utilisateur = :uid')->setParameter('uid', (int) $uid);
        }
        if ($module = $request->query->get('module')) {
            $qb->andWhere('j.module LIKE :m')->setParameter('m', "%$module%");
        }
        if ($niveauId = $request->query->get('niveauId')) {
            $qb->andWhere('j.niveauId = :n')->setParameter('n', (int) $niveauId);
        }
        if ($dd = $request->query->get('dateDebut')) {
            $qb->andWhere('j.dateAction >= :dd')->setParameter('dd', new \DateTimeImmutable($dd));
        }
        if ($df = $request->query->get('dateFin')) {
            $qb->andWhere('j.dateAction <= :df')
               ->setParameter('df', new \DateTimeImmutable($df . ' 23:59:59'));
        }

        $journaux = $qb->getQuery()->getResult();

        $csv  = "\"Date\",\"Utilisateur\",\"Module\",\"Action\",\"Détails\",\"Niveau\",\"IP\"\n";
        foreach ($journaux as $j) {
            $u    = $j->getUtilisateur();
            $nom  = $u ? $u->getPrenom() . ' ' . $u->getNom() : 'Système';
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%d,\"%s\"\n",
                $j->getDateAction()->format('d/m/Y H:i'),
                str_replace('"', '""', $nom),
                str_replace('"', '""', $j->getModule()),
                str_replace('"', '""', $j->getAction()),
                str_replace('"', '""', $j->getDetails() ?? ''),
                $j->getNiveauId(),
                $j->getAdresseIp() ?? ''
            );
        }

        $filename = 'journaux_' . date('Ymd') . '.csv';
        $response = new Response("\xEF\xBB\xBF" . $csv); // BOM UTF-8 pour Excel
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"$filename\"");

        return $response;
    }

    // ── SERIALIZE ─────────────────────────────────────────────────────────
    private function serialize(Journal $j): array
    {
        $u = $j->getUtilisateur();
        return [
            'id'          => $j->getId(),
            'utilisateur' => $u ? $u->getPrenom() . ' ' . $u->getNom() : 'Système',
            'module'      => $j->getModule(),
            'action'      => $j->getAction(),
            'details'     => $j->getDetails(),
            'niveauId'    => $j->getNiveauId(),
            'dateAction'  => $j->getDateAction()->format('c'),
            'entiteId'    => $j->getEntiteId(),
            'adresseIp'   => $j->getAdresseIp(),
        ];
    }
}
