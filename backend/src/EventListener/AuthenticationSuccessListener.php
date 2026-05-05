<?php
namespace App\EventListener;

use App\Entity\Utilisateur;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

class AuthenticationSuccessListener
{
    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof Utilisateur) return;

        $data['user'] = [
            'id'          => $user->getId(),
            'nom'         => $user->getNom(),
            'prenom'      => $user->getPrenom(),
            'email'       => $user->getEmail(),
            'role'        => $user->getRoleNom(),
            'permissions' => $user->getPermissions(),
        ];

        $event->setData($data);
    }
}