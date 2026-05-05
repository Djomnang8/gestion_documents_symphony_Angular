<?php
namespace App\EventListener;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JwtCreatedListener
{
    public function __construct(private EntityManagerInterface $em) {}

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof Utilisateur) return;

        // Met à jour la dernière connexion
        $user->setDerniereConnexion(new \DateTimeImmutable());
        $this->em->flush();

        // Enrichit le payload JWT
        // serviceNom est lu par agent-layout.ts pour remplacer "Cabinet Juridique"
        $payload = $event->getData();
        $payload['id']          = $user->getId();
        $payload['nom']         = $user->getNom();
        $payload['prenom']      = $user->getPrenom();
        $payload['email']       = $user->getEmail();
        $payload['role']        = $user->getRoleNom();
        $payload['permissions'] = $user->getPermissions();
        $payload['serviceId']   = $user->getService()?->getId();
        $payload['serviceNom']  = $user->getService()?->getNom() ?? '';

        $event->setData($payload);
    }
}