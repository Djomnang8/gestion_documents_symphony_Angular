<?php
namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profil', name: 'profil_')]
class ProfilController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private UserPasswordHasherInterface $hasher
    ) {}

    #[Route('', name: 'show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        return $this->json([
            'id'          => $user->getId(),
            'nom'         => $user->getNom(),
            'prenom'      => $user->getPrenom(),
            'email'       => $user->getEmail(),
            'telephone'   => $user->getTelephone(),
            'role'        => $user->getRoleNom(),
            'permissions' => $user->getPermissions(),
            'serviceId'   => $user->getService()?->getId(),
            'serviceNom'  => $user->getService()?->getNom() ?? '',
            'service'     => $user->getService()
                ? ['id' => $user->getService()->getId(), 'nom' => $user->getService()->getNom()]
                : null,
        ]);
    }

    #[Route('', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['nom']))       $user->setNom($data['nom']);
        if (isset($data['prenom']))    $user->setPrenom($data['prenom']);
        if (isset($data['telephone'])) $user->setTelephone($data['telephone']);

        if (isset($data['ancienMotDePasse']) && isset($data['nouveauMotDePasse'])) {
            if (!$this->hasher->isPasswordValid($user, $data['ancienMotDePasse'])) {
                return $this->json(['message' => 'Ancien mot de passe incorrect'], 400);
            }
            $user->setMotDePasseHash(
                $this->hasher->hashPassword($user, $data['nouveauMotDePasse'])
            );
        }

        $this->em->flush();
        return $this->json(['message' => 'Profil mis à jour']);
    }
}
