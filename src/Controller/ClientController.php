<?php

namespace App\Controller;

use App\Repository\CommandeRepository;
use App\Repository\ReservationRepository;
use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Reservation; // Assurez-vous d'avoir cette entité
use App\Enum\StatutCommande;
use App\Repository\PlatRepository;
use App\Repository\TableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/client')]
class ClientController extends AbstractController
{
    #[Route('/dashboard', name: 'app_client_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('client/index.html.twig');
    }

    // --- PARTIE RÉSERVATION ---

    #[Route('/reserver-table', name: 'app_client_reserver_table', methods: ['GET'])]
    public function reserverTable(): Response
    {
        return $this->render('client/reserver_table.html.twig');
    }

    #[Route('/reserver-table/valider', name: 'app_client_reserver_table_post', methods: ['POST'])]
    public function reserverTablePost(Request $request, EntityManagerInterface $em): Response
    {
        $nbPersonnes = $request->request->get('personnes');
        $dateHeure = $request->request->get('dateHeure');
        $message = $request->request->get('message');

        // Ici, vous devriez créer une entité Reservation
        // Exemple (si vous avez l'entité) :
        /*
        $res = new Reservation();
        $res->setNbPersonnes((int)$nbPersonnes);
        $res->setDate(new \DateTime($dateHeure));
        $res->setCommentaire($message);
        $res->setUser($this->getUser());
        $em->persist($res);
        $em->flush();
        */

        $this->addFlash('success', 'Votre demande de réservation a été envoyée !');
        return $this->redirectToRoute('app_client_dashboard');
    }

    // --- PARTIE COMMANDE ---

    #[Route('/passer-commande', name: 'app_client_passer_commande', methods: ['GET'])]
    public function passerCommande(PlatRepository $platRepo, TableRepository $tableRepo): Response
    {
        $plats = $platRepo->findBy(['disponible' => true]);
        $tables = $tableRepo->findBy([], ['numero' => 'ASC']);

        return $this->render('client/passer_commande.html.twig', [
            'plats' => $plats,
            'tables' => $tables,
        ]);
    }

    #[Route('/passer-commande/valider', name: 'app_client_passer_commande_post', methods: ['POST'])]
    public function passerCommandePost(
        Request $request,
        PlatRepository $platRepo,
        TableRepository $tableRepo,
        EntityManagerInterface $em
    ): Response {
        $items = $request->request->all('items');
        $tableId = $request->request->get('tableId');

        if (!$tableId) {
            $this->addFlash('error', 'Veuillez sélectionner une table.');
            return $this->redirectToRoute('app_client_passer_commande');
        }

        $table = $tableRepo->find($tableId);
        $commande = new Commande();
        $commande->setTableCommande($table);
        $commande->setDate(new \DateTime());
        $commande->setStatus(StatutCommande::en_cours);

        $total = 0;
        foreach ($items as $platId => $quantite) {
            if ($quantite > 0) {
                $plat = $platRepo->find($platId);
                if ($plat) {
                    $ligne = new LigneCommande();
                    $ligne->setPlat($plat);
                    $ligne->setQuantite((int)$quantite);
                    $ligne->setPrixUnitaire($plat->getPrix());
                    $ligne->setCommande($commande);
                    $em->persist($ligne);
                    $total += ($plat->getPrix() * $quantite);
                }
            }
        }

        $commande->setTotal($total);
        $em->persist($commande);
        $em->flush();

        $this->addFlash('success', 'Commande validée !');
        return $this->redirectToRoute('app_client_dashboard');
    }
    //partie historique
    /**
     * Affiche l'historique complet (Commandes + Réservations)
     */
    #[Route('/historique', name: 'app_client_historique', methods: ['GET'])]
    public function historique(
        CommandeRepository $commandeRepo,
        ReservationRepository $reservationRepo
    ): Response {
        // Sécurité : Seul un client peut voir son historique
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        // Récupération de l'utilisateur connecté
        $user = $this->getUser();

        // Récupération des données filtrées par l'utilisateur connecté
        // On trie par date décroissante (la plus récente d'abord)
        $reservations = $reservationRepo->findBy(['client' => $user], ['dateHeure' => 'DESC']);
        $commandes = $commandeRepo->findBy(['client' => $user], ['date' => 'DESC']);

        return $this->render('client/historique.html.twig', [
            'reservations' => $reservations,
            'commandes' => $commandes,
        ]);
    }
}
