<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Reservation;
use App\Entity\Plat;
use App\Enum\StatutCommande; // ✅ CHANGEMENT
use App\Repository\PlatRepository;
use Doctrine\ORM\Query\Expr\Join;
use App\Repository\CommandeRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    // =======================
    // RÉSERVATION (GET)
    // =======================
    #[Route('/reserver-table', name: 'reserver_table', methods: ['GET'])]
    public function reserverTable(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        return $this->render('client/reserver_table.html.twig');
    }

    // =======================
    // RÉSERVATION (POST)
    // =======================
    #[Route('/reserver-table', name: 'reserver_table_post', methods: ['POST'])]
    public function reserverTablePost(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        $personnes = (int) $request->request->get('personnes');
        $dateHeureStr = $request->request->get('dateHeure');
        $message = $request->request->get('message');

        if ($personnes <= 0 || !$dateHeureStr) {
            $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
            return $this->redirectToRoute('app_client_reserver_table');
        }

        try {
            $dateHeure = new \DateTime($dateHeureStr);
        } catch (\Exception) {
            $this->addFlash('error', 'Date invalide.');
            return $this->redirectToRoute('app_client_reserver_table');
        }

        if ($dateHeure < new \DateTime()) {
            $this->addFlash('error', 'La date doit être dans le futur.');
            return $this->redirectToRoute('app_client_reserver_table');
        }

        $reservation = new Reservation();
        $reservation->setClient($this->getUser()); // ✅ LIAISON CLIENT
        $reservation->setDateHeure($dateHeure);
        $reservation->setPersonnes($personnes);
        $reservation->setMessage($message);

        $em->persist($reservation);
        $em->flush(); // 💾 SAUVEGARDE EN BASE

        $this->addFlash(
            'success',
            'Réservation confirmée pour le ' . $dateHeure->format('d/m/Y à H:i')
        );

        return $this->redirectToRoute('app_client_historique');
    }

    // =======================
    // PASSER COMMANDE (PAGE)
    // =======================
    #[Route('/passer-commande', name: 'passer_commande', methods: ['GET'])]
    public function passerCommande(PlatRepository $platRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        $plats = $platRepository->findBy(['disponible' => true]);

        return $this->render('client/passer_commande.html.twig', [
            'plats' => $plats,
        ]);
    }
    #[Route('/passer-commande', name: 'passer_commande_post', methods: ['POST'])]
    public function passerCommandePost(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        $items = $request->request->all('items');
        $modePaiement = $request->request->get('modePaiement', 'Espèces');

        if (!$items || count($items) === 0) {
            $this->addFlash('error', 'Votre commande est vide.');
            return $this->redirectToRoute('app_client_passer_commande');
        }

        $commande = new Commande();
        $commande->setClient($this->getUser());
        $commande->setDate(new \DateTime());
        $commande->setModePaiement($modePaiement);
        $commande->setStatus(StatutCommande::en_cours);

        $total = 0;

        foreach ($items as $platId => $quantite) {
            if ($quantite <= 0) continue;

            $plat = $em->getRepository(\App\Entity\Plat::class)->find($platId);
            if (!$plat) continue;

            $ligne = new LigneCommande();
            $ligne->setCommande($commande);
            $ligne->setPlat($plat);
            $ligne->setQuantite($quantite);
            $ligne->setPrixUnitaire($plat->getPrix());

            $total += $plat->getPrix() * $quantite;

            $em->persist($ligne);
        }

        $commande->setTotal($total);

        $em->persist($commande);
        $em->flush();

        $this->addFlash('success', 'Commande enregistrée avec succès.');

        return $this->redirectToRoute('app_client_historique');
    }




    // =======================
    // HISTORIQUE
    // =======================
    #[Route('/historique', name: 'historique')]
    public function historique(
        CommandeRepository $commandeRepository,
        ReservationRepository $reservationRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        $client = $this->getUser();

        $commandes = $commandeRepository->createQueryBuilder('c')
            ->addSelect('lc')
            ->addSelect('plat')
            ->leftJoin('c.ligneCommandes', 'lc')   // ✅ NOM CORRECT
            ->leftJoin('lc.plat', 'plat')
            ->where('c.client = :client')
            ->setParameter('client', $client)
            ->orderBy('c.date', 'DESC')
            ->getQuery()
            ->getResult();

        $reservations = $reservationRepository->findBy(
            ['client' => $client],
            ['dateHeure' => 'DESC']
        );

        return $this->render('client/historique.html.twig', [
            'commandes' => $commandes,
            'reservations' => $reservations,
        ]);
    }

    // =======================
    // VALIDER COMMANDE (AJAX)
    // =======================
    #[Route('/valider-commande', name: 'valider_commande', methods: ['POST'])]
    public function validerCommande(

        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        die('ROUTE VALIDER COMMANDE APPELÉE');

        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['items'], $data['total'], $data['modePaiement'])) {
            return new JsonResponse(['success' => false], 400);
        }

        $commande = new Commande();
        $commande->setClient($this->getUser());
        $commande->setDate(new \DateTime());
        $commande->setTotal((float) $data['total']);
        $commande->setModePaiement($data['modePaiement']);
        $commande->setStatus(\App\Enum\StatutCommande::en_cours); // ✅ ENUM

        $em->persist($commande);

        foreach ($data['items'] as $item) {
            $plat = $em->getRepository(\App\Entity\Plat::class)->find($item['id']);
            if (!$plat) continue;

            $ligne = new LigneCommande();
            $ligne->setCommande($commande);
            $ligne->setPlat($plat);
            $ligne->setQuantite((int) ($item['quantite'] ?? 1));
            $ligne->setPrixUnitaire($plat->getPrix());

            $em->persist($ligne);
        }

        $em->flush(); // 💾 ICI la commande est enregistrée

        return new JsonResponse(['success' => true]);
    }

}
