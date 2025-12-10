<?php
// src/Controller/ServeurController.php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Table;
use App\Enum\StatutCommande;
use App\Repository\CommandeRepository;
use App\Repository\PlatRepository;
use App\Repository\TableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/serveur', name: 'app_serveur_')]
class ServeurController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    #[Route('/', name: 'index')]
    public function dashboard(TableRepository $tableRepo, CommandeRepository $commandeRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SERVEUR');

        $tables = $tableRepo->findBy([], ['numero' => 'ASC']);
        $commandesEnCours = $commandeRepo->findByStatutNonTermine();

        return $this->render('serveur/index.html.twig', [
            'tables' => $tables,
            'commandesEnCours' => $commandesEnCours,
        ]);
    }

    // Étape 1 : Afficher le menu pour une table libre
    #[Route('/prendre-commande/table/{id}', name: 'prendre_commande_table')]
    public function prendreCommandeTable(Table $table, PlatRepository $platRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SERVEUR');

        if (!$table->isEstlibre()) {
            $this->addFlash('danger', 'Cette table est déjà occupée !');
            return $this->redirectToRoute('app_serveur_dashboard');
        }

        $plats = $platRepo->findBy(['disponible' => true], ['nom' => 'ASC']);

        return $this->render('serveur/prendre_commande.html.twig', [
            'table' => $table,
            'plats' => $plats,
        ]);
    }

    // Étape 2 : Valider la commande (SANS notes)
    #[Route('/prendre-commande/table/{id}/valider', name: 'prendre_commande_valider', methods: ['POST'])]
    public function validerCommande(
        Table $table,
        Request $request,
        EntityManagerInterface $em,
        PlatRepository $platRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_SERVEUR');

        if (!$table->isEstlibre()) {
            $this->addFlash('danger', 'Cette table est déjà occupée !');
            return $this->redirectToRoute('app_serveur_dashboard');
        }

        $platsIds = $request->request->all('plats'); // [plat_id => quantite]

        if (empty($platsIds) || array_sum($platsIds) == 0) {
            $this->addFlash('warning', 'Veuillez sélectionner au moins un plat.');
            return $this->redirectToRoute('app_serveur_prendre_commande_table', ['id' => $table->getId()]);
        }

        // Créer la commande
        $commande = new Commande();
        $commande->setTableCommande($table);
        $commande->setDate(new \DateTime());
        $commande->setStatus(StatutCommande::en_cours);
        $commande->setTotal(0);

        $total = 0;

        foreach ($platsIds as $platId => $quantite) {
            if ($quantite > 0) {
                $plat = $platRepo->find($platId);
                if ($plat && $plat->isDisponible()) {
                    $ligne = new LigneCommande();
                    $ligne->setPlat($plat);
                    $ligne->setQuantite((int)$quantite);
                    $ligne->setPrixUnitaire($plat->getPrix());
                    $ligne->setCommande($commande);

                    $commande->addLigneCommande($ligne);

                    $total += $plat->getPrix() * $quantite;
                }
            }
        }

        $commande->setTotal($total);

        // Occuper la table
        $table->setEstlibre(false);

        $em->persist($commande);
        $em->flush();

        $this->addFlash('success', 'Commande prise avec succès pour la table ' . $table->getNumero() . ' !');
        return $this->redirectToRoute('app_serveur_dashboard');
    }

    // Liste des commandes en cours
    #[Route('/commandes-en-cours', name: 'commandes_en_cours')]
    public function commandesEnCours(CommandeRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SERVEUR');

        $commandes = $repo->findByStatutNonTermine();

        return $this->render('serveur/commandes_en_cours.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    // Changer le statut d'une commande (EN_COURS → PRÊTE → SERVIE)
    #[Route('/commande/{id}/statut/{nouveauStatut}', name: 'commande_changer_statut', methods: ['POST'])]
    public function changerStatutCommande(Commande $commande, string $nouveauStatut, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SERVEUR');

        // Utilise les vrais noms des cases (en minuscules)
        $statutsAutorises = ['prete', 'servie'];

        if (!in_array($nouveauStatut, $statutsAutorises)) {
            $this->addFlash('danger', 'Statut invalide.');
            return $this->redirectToRoute('app_serveur_commandes_en_cours');
        }

        // Utilise la bonne syntaxe pour accéder à la case de l'enum
        $statutEnum = match ($nouveauStatut) {
            'prete' => StatutCommande::prete,
            'servie' => StatutCommande::servie,
            default => throw new \InvalidArgumentException('Statut invalide'),
        };

        $commande->setStatus($statutEnum);

        // Libérer la table si la commande est servie
        if ($nouveauStatut === 'servie') {
            $table = $commande->getTableCommande();
            if ($table) {
                $table->setEstlibre(true);
            }
        }

        $em->flush();

        $this->addFlash('success', 'Statut mis à jour : ' . $statutEnum->value);
        return $this->redirectToRoute('app_serveur_commandes_en_cours');
    }
}
