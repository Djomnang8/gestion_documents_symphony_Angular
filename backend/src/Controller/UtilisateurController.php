<?php
namespace App\Controller;

use App\Entity\Journal;
use App\Entity\Role;
use App\Entity\Service;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/utilisateurs', name: 'utilisateur_')]
class UtilisateurController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface      $em,
        private UserPasswordHasherInterface $hasher,
        private Security                    $security
    ) {}

    // ── LISTE ──────────────────────────────────────────────────────────────
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->em->getRepository(Utilisateur::class)
            ->createQueryBuilder('u')
            ->where('u.estSupprime = false');

        $statut = $request->query->get('statut');
        if ($statut === 'actif') {
            $qb->andWhere('u.estActif = true AND u.estListeNoire = false');
        } elseif ($statut === 'inactif') {
            $qb->andWhere('u.estActif = false');
        } elseif ($statut === 'listenoire') {
            $qb->andWhere('u.estListeNoire = true');
        }

        return $this->json(array_map([$this, 'serialize'], $qb->getQuery()->getResult()));
    }

    // ── RÔLES DISPONIBLES ──────────────────────────────────────────────────
    #[Route('/roles', name: 'roles', methods: ['GET'])]
    public function getRoles(): JsonResponse
    {
        $roles = $this->em->getRepository(Role::class)->findAll();
        return $this->json(array_map(fn($r) => [
            'id'          => $r->getId(),
            'nom'         => $r->getNom(),
            'description' => $r->getDescription(),
            'permissions' => array_map(fn($p) => $p->getNom(), $r->getPermissions()->toArray()),
        ], $roles));
    }

    // ── SERVICES DISPONIBLES ───────────────────────────────────────────────
    #[Route('/services', name: 'services', methods: ['GET'])]
    public function getServices(): JsonResponse
    {
        $services = $this->em->getRepository(Service::class)->findBy(['estActif' => true]);
        return $this->json(array_map(fn($s) => [
            'id'          => $s->getId(),
            'nom'         => $s->getNom(),
            'description' => $s->getDescription(),
        ], $services));
    }

    // ── DÉTAIL ─────────────────────────────────────────────────────────────
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->em->getRepository(Utilisateur::class)->find($id);
        if (!$user || $user->isEstSupprime()) {
            return $this->json(['message' => 'Utilisateur non trouvé'], 404);
        }
        return $this->json($this->serialize($user));
    }

    // ── CRÉER ──────────────────────────────────────────────────────────────
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Vérifier unicité email
        $existant = $this->em->getRepository(Utilisateur::class)
            ->createQueryBuilder('u')
            ->where('u.email = :email AND u.estSupprime = false')
            ->setParameter('email', $data['email'] ?? '')
            ->getQuery()->getOneOrNullResult();

        if ($existant) {
            return $this->json(['message' => 'Cet email est déjà utilisé.'], 400);
        }

        $user = new Utilisateur();
        $user->setNom($data['nom'] ?? '')
             ->setPrenom($data['prenom'] ?? '')
             ->setEmail($data['email'] ?? '')
             ->setTelephone($data['telephone'] ?? null)
             ->setTypeUtilisateur($data['typeUtilisateur'] ?? $data['role'] ?? 'Agent')
             ->setMotDePasseHash(
                 $this->hasher->hashPassword($user, $data['motDePasse'] ?? 'Password123!')
             );

        $roleNom = $data['roleNom'] ?? $data['role'] ?? null;
        if ($roleNom) {
            $role = $this->em->getRepository(Role::class)->findOneBy(['nom' => $roleNom]);
            if ($role) {
                $user->getRoles_()->add($role);
            } else {
                return $this->json(['message' => 'Rôle introuvable.'], 400);
            }
        }

        if (isset($data['serviceId']) && $data['serviceId']) {
            $service = $this->em->getRepository(Service::class)->find($data['serviceId']);
            if ($service) {
                $user->setService($service);
            }
        }

        $this->em->persist($user);
        $this->em->flush();

        $this->logAction('utilisateurs.creer', "Création utilisateur {$user->getEmail()}");

        return $this->json(array_merge($this->serialize($user), ['message' => 'Utilisateur créé.']), 201);
    }

    // ── MODIFIER ───────────────────────────────────────────────────────────
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->em->getRepository(Utilisateur::class)->find($id);
        if (!$user || $user->isEstSupprime()) {
            return $this->json(['message' => 'Utilisateur non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);

        // Vérifier unicité email si changé
        if (isset($data['email']) && $data['email'] !== $user->getEmail()) {
            $doublon = $this->em->getRepository(Utilisateur::class)
                ->createQueryBuilder('u')
                ->where('u.email = :email AND u.estSupprime = false AND u.id != :id')
                ->setParameter('email', $data['email'])
                ->setParameter('id', $id)
                ->getQuery()->getOneOrNullResult();
            if ($doublon) {
                return $this->json(['message' => 'Cet email est déjà utilisé.'], 400);
            }
        }

        if (isset($data['nom']))       $user->setNom($data['nom']);
        if (isset($data['prenom']))    $user->setPrenom($data['prenom']);
        if (isset($data['email']))     $user->setEmail($data['email']);
        if (isset($data['telephone'])) $user->setTelephone($data['telephone']);
        if (isset($data['estActif']))  $user->setEstActif((bool) $data['estActif']);
        if (isset($data['motDePasse']) && $data['motDePasse']) {
            $user->setMotDePasseHash($this->hasher->hashPassword($user, $data['motDePasse']));
        }

        // Changer le rôle si fourni
        $roleNom = $data['role'] ?? $data['roleNom'] ?? null;
        if ($roleNom) {
            $role = $this->em->getRepository(Role::class)->findOneBy(['nom' => $roleNom]);
            if (!$role) {
                return $this->json(['message' => 'Rôle introuvable.'], 400);
            }
            $user->getRoles_()->clear();
            $user->getRoles_()->add($role);
            $user->setTypeUtilisateur($roleNom);
        }

        if (isset($data['serviceId'])) {
            $service = $this->em->getRepository(Service::class)->find($data['serviceId']);
            if ($service) {
                $user->setService($service);
            }
        }

        $this->em->flush();
        $this->logAction('utilisateurs.modifier', "Modification {$user->getEmail()}");

        return $this->json(array_merge($this->serialize($user), ['message' => 'Utilisateur modifié avec succès.']));
    }

    // ── CHANGER MOT DE PASSE ───────────────────────────────────────────────
    #[Route('/{id}/mot-de-passe', name: 'mot_de_passe', methods: ['PUT', 'PATCH'])]
    public function changerMotDePasse(int $id, Request $request): JsonResponse
    {
        $user = $this->em->getRepository(Utilisateur::class)->find($id);
        if (!$user) {
            return $this->json(['message' => 'Non trouvé'], 404);
        }

        $data       = json_decode($request->getContent(), true);
        $ancienMdp  = $data['ancienMotDePasse']  ?? '';
        $nouveauMdp = $data['nouveauMotDePasse'] ?? '';

        if (empty($ancienMdp) || empty($nouveauMdp)) {
            return $this->json(['message' => 'Les deux mots de passe sont requis'], 400);
        }
        if (strlen($nouveauMdp) < 6) {
            return $this->json(['message' => 'Minimum 6 caractères'], 400);
        }
        if (!$this->hasher->isPasswordValid($user, $ancienMdp)) {
            return $this->json(['message' => 'Mot de passe actuel incorrect'], 400);
        }

        $user->setMotDePasseHash($this->hasher->hashPassword($user, $nouveauMdp));
        $this->em->flush();

        return $this->json(['message' => 'Mot de passe modifié avec succès']);
    }

    // ── ACTIVER ────────────────────────────────────────────────────────────
    #[Route('/{id}/activer', name: 'activer', methods: ['PUT', 'PATCH'])]
    public function activer(int $id): JsonResponse
    {
        $user = $this->em->getRepository(Utilisateur::class)->find($id);
        if (!$user) {
            return $this->json(['message' => 'Non trouvé'], 404);
        }
        $user->setEstActif(true)->setEstListeNoire(false)->setMotifListeNoire(null);
        $this->em->flush();
        $this->logAction('utilisateurs.activer', "Activation {$user->getEmail()}");
        return $this->json(['message' => 'Compte activé.']);
    }

    // ── DÉSACTIVER ─────────────────────────────────────────────────────────
    #[Route('/{id}/desactiver', name: 'desactiver', methods: ['PUT', 'PATCH'])]
    public function desactiver(int $id): JsonResponse
    {
        $user = $this->em->getRepository(Utilisateur::class)->find($id);
        if (!$user) {
            return $this->json(['message' => 'Non trouvé'], 404);
        }
        $user->setEstActif(false);
        $this->em->flush();
        $this->logAction('utilisateurs.desactiver', "Désactivation {$user->getEmail()}");
        return $this->json(['message' => 'Compte désactivé.']);
    }

    // ── LISTE NOIRE ────────────────────────────────────────────────────────
    #[Route('/{id}/listenoire', name: 'listenoire', methods: ['PUT', 'PATCH'])]
    public function mettreListeNoire(int $id, Request $request): JsonResponse
    {
        $user = $this->em->getRepository(Utilisateur::class)->find($id);
        if (!$user) {
            return $this->json(['message' => 'Non trouvé'], 404);
        }
        $data = json_decode($request->getContent(), true);

        if (empty($data['motif'])) {
            return $this->json(['message' => 'Le motif est obligatoire.'], 400);
        }

        $user->setEstListeNoire(true)
             ->setMotifListeNoire($data['motif'])
             ->setEstActif(false);

        $this->em->flush();
        $this->logAction('utilisateurs.blacklist', "Liste noire {$user->getEmail()}");

        return $this->json(['message' => 'Utilisateur mis en liste noire.']);
    }

    // ── CHANGER RÔLE ───────────────────────────────────────────────────────
    #[Route('/{id}/role', name: 'changer_role', methods: ['PUT', 'PATCH'])]
    public function changerRole(int $id, Request $request): JsonResponse
    {
        $user = $this->em->getRepository(Utilisateur::class)->find($id);
        if (!$user) {
            return $this->json(['message' => 'Non trouvé'], 404);
        }
        $data = json_decode($request->getContent(), true);
        $role = $this->em->getRepository(Role::class)->findOneBy(['nom' => $data['role'] ?? '']);
        if (!$role) {
            return $this->json(['message' => 'Rôle invalide'], 400);
        }
        $user->getRoles_()->clear();
        $user->getRoles_()->add($role);
        $user->setTypeUtilisateur($role->getNom());
        $this->em->flush();
        return $this->json(['message' => 'Rôle mis à jour.']);
    }

    // ── SUPPRIMER (soft delete) ────────────────────────────────────────────
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->em->getRepository(Utilisateur::class)->find($id);
        if (!$user) {
            return $this->json(['message' => 'Non trouvé'], 404);
        }
        $user->setEstSupprime(true)->setEstActif(false);
        $this->em->flush();
        return $this->json(null, 204);
    }

    // ── SÉRIALISATION ──────────────────────────────────────────────────────
    private function serialize(Utilisateur $u): array
    {
        return [
            'id'                => $u->getId(),
            'nom'               => $u->getNom(),
            'prenom'            => $u->getPrenom(),
            'email'             => $u->getEmail(),
            'telephone'         => $u->getTelephone(),
            'estActif'          => $u->isEstActif(),
            'estListeNoire'     => $u->isEstListeNoire(),
            'motifListeNoire'   => $u->getMotifListeNoire(),
            'typeUtilisateur'   => $u->getTypeUtilisateur(),
            'role'              => $u->getRoleNom(),
            'permissions'       => $u->getPermissions(),
            'serviceId'         => $u->getService()?->getId(),
            'service'           => $u->getService()
                ? ['id' => $u->getService()->getId(), 'nom' => $u->getService()->getNom()]
                : null,
            'derniereConnexion' => $u->getDerniereConnexion()?->format('c'),
        ];
    }

    // ── JOURNALISATION ─────────────────────────────────────────────────────
    private function logAction(string $action, string $details): void
    {
        $currentUser = $this->security->getUser();
        $j = new Journal();
        $j->setModule('Utilisateurs')->setAction($action)->setDetails($details)->setNiveauId(1);
        if ($currentUser instanceof Utilisateur) {
            $j->setUtilisateur($currentUser);
        }
        $this->em->persist($j);
        $this->em->flush();
    }
}
