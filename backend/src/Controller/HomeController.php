<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(ParameterBagInterface $params): RedirectResponse
    {
        $frontendUrl = $params->get('frontend_url');
        return new RedirectResponse($frontendUrl);
    }
}