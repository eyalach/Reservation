<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Reservation;
use App\Enum\StatutCommande;
use App\Repository\CommandeRepository;
use App\Repository\ReservationRepository;
use App\Repository\PlatRepository;
use App\Repository\TableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client')]
// Assure que seul un utilisateur connecté peut accéder à ces routes
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ClientController extends AbstractController
{
    #[Route('/dashboard', name: 'app_client_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('client/index.html.twig');
    }

    // ==================== PARTIE RÉSERVATION ====================

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

        if (!$dateHeure || !$nbPersonnes) {
            $this->addFlash('danger', 'Veuillez remplir les champs obligatoires.');
            return $this->redirectToRoute('app_client_reserver_table');
        }

        // Création de la réservation liée au client connecté
        $res = new Reservation();
        $res->setNb_places((int)$nbPersonnes); // Vérifiez si c'est setNbPlaces ou setNb_places selon votre entité
        $res->setDateHeure(new \DateTime($dateHeure));
        $res->setMessage($message);
        $res->setClient($this->getUser()); // <--- CRUCIAL pour l'historique

        $em->persist($res);
        $em->flush();

        $this->addFlash('success', 'Votre demande de réservation a été enregistrée avec succès !');
        return $this->redirectToRoute('app_client_dashboard');
    }

    // ==================== PARTIE COMMANDE ====================

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
        $modePaiement = $request->request->get('modePaiement', 'Espèces'); // Par défaut espèces

        if (!$tableId) {
            $this->addFlash('danger', 'Veuillez sélectionner une table.');
            return $this->redirectToRoute('app_client_passer_commande');
        }

        $table = $tableRepo->find($tableId);

        // Initialisation de la commande
        $commande = new Commande();
        $commande->setTableCommande($table);
        $commande->setDate(new \DateTime());
        $commande->setStatus(StatutCommande::en_cours);
        $commande->setClient($this->getUser()); // <--- CRUCIAL pour l'historique
        $commande->setModePaiement($modePaiement);

        $total = 0;
        $hasItems = false;

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
                    $hasItems = true;
                }
            }
        }

        if (!$hasItems) {
            $this->addFlash('danger', 'Votre panier est vide.');
            return $this->redirectToRoute('app_client_passer_commande');
        }

        $commande->setTotal($total);
        $em->persist($commande);
        $em->flush();

        $this->addFlash('success', 'Votre commande a été validée ! Retrouvez-la dans votre historique.');
        return $this->redirectToRoute('app_client_dashboard');
    }

    // ==================== PARTIE HISTORIQUE ====================

    #[Route('/historique', name: 'app_client_historique', methods: ['GET'])]
    public function historique(
        CommandeRepository $commandeRepo,
        ReservationRepository $reservationRepo
    ): Response {

        $user = $this->getUser();

        // Récupération des données filtrées par l'utilisateur connecté
        // On vérifie que la propriété dans l'entité est bien 'client'
        $reservations = $reservationRepo->findBy(['client' => $user], ['dateHeure' => 'DESC']);
        $commandes = $commandeRepo->findBy(['client' => $user], ['date' => 'DESC']);

        return $this->render('client/historique.html.twig', [
            'reservations' => $reservations,
            'commandes' => $commandes,
        ]);
    }
}
