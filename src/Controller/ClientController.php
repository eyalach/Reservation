<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/client', name: 'app_client_')]
class ClientController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    #[Route('/', name: 'index')]
    public function dashboard(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        return $this->render('client/index.html.twig');
    }

    #[Route('/reserver-table', name: 'reserver_table', methods: ['GET'])]
    public function reserverTable(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        return $this->render('client/reserver_table.html.twig');
    }

    #[Route('/reserver-table', name: 'reserver_table_post', methods: ['POST'])]
    public function reserverTablePost(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        // Ici tu as déjà le code pour sauvegarder la réservation
        // (celui que je t'ai donné avant)
        $this->addFlash('success', 'Réservation confirmée !');
        return $this->redirectToRoute('app_client_dashboard');
    }

    #[Route('/passer-commande', name: 'passer_commande')]
    public function passerCommande(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        // ton code pour les plats
        return $this->render('client/passer_commande.html.twig', ['plats' => []]);
    }

    #[Route('/historique', name: 'historique')]
    public function historique(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        return $this->render('client/historique.html.twig');
    }
}
