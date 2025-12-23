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
    /**
     * Affiche le tableau de bord du serveur (Tables + Commandes en cours)
     */
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

    /**
     * Étape 1 : Afficher le menu pour une table spécifique
     */
    #[Route('/prendre-commande/table/{id}', name: 'prendre_commande_table')]
    public function prendreCommandeTable(Table $table, PlatRepository $platRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SERVEUR');

        // Si la table n'est pas libre, on ne peut pas reprendre une commande ici
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

    /**
     * Étape 2 : Traitement du formulaire de commande
     */
    #[Route('/prendre-commande/table/{id}/valider', name: 'prendre_commande_valider', methods: ['POST'])]
    public function validerCommande(
        Table $table,
        Request $request,
        EntityManagerInterface $em,
        PlatRepository $platRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_SERVEUR');

        // 1. Vérification de sécurité sur la table
        if (!$table->isEstlibre()) {
            $this->addFlash('danger', 'Cette table est déjà occupée !');
            return $this->redirectToRoute('app_serveur_dashboard');
        }

        // 2. Récupération des données du formulaire [id_plat => quantite]
        $platsData = $request->request->all('plats');

        if (empty($platsData)) {
            $this->addFlash('warning', 'Aucune donnée reçue.');
            return $this->redirectToRoute('app_serveur_prendre_commande_table', ['id' => $table->getId()]);
        }

        // 3. Création de l'entité Commande
        $commande = new Commande();
        $commande->setTableCommande($table);
        $commande->setDate(new \DateTime());
        $commande->setStatus(StatutCommande::en_cours);
        $commande->setTotal(0);

        $totalCommande = 0;
        $aDesArticles = false;

        // 4. Boucle sur les plats sélectionnés
        foreach ($platsData as $platId => $quantite) {
            $quantite = (int)$quantite;

            if ($quantite > 0) {
                $plat = $platRepo->find($platId);

                if ($plat && $plat->isDisponible()) {
                    $aDesArticles = true;

                    $ligne = new LigneCommande();
                    $ligne->setPlat($plat);
                    $ligne->setQuantite($quantite);
                    $ligne->setPrixUnitaire($plat->getPrix());
                    $ligne->setCommande($commande);

                    $em->persist($ligne);
                    $totalCommande += ($plat->getPrix() * $quantite);
                }
            }
        }

        // 5. Finalisation
        if (!$aDesArticles) {
            $this->addFlash('warning', 'Veuillez sélectionner au moins un plat avec une quantité supérieure à 0.');
            return $this->redirectToRoute('app_serveur_prendre_commande_table', ['id' => $table->getId()]);
        }

        $commande->setTotal($totalCommande);
        $table->setEstlibre(false); // La table devient occupée

        $em->persist($commande);
        $em->flush();

        $this->addFlash('success', 'Commande validée avec succès pour la table ' . $table->getNumero());
        return $this->redirectToRoute('app_serveur_dashboard');
    }

    /**
     * Liste toutes les commandes qui ne sont pas encore terminées
     */
    #[Route('/commandes-en-cours', name: 'commandes_en_cours')]
    public function commandesEnCours(CommandeRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SERVEUR');

        $commandes = $repo->findByStatutNonTermine();

        return $this->render('serveur/commandes_en_cours.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    /**
     * Change le statut : en_cours -> prete -> servie
     */
    #[Route('/commande/{id}/statut/{nouveauStatut}', name: 'commande_changer_statut', methods: ['POST'])]
    public function changerStatutCommande(
        Commande $commande,
        string $nouveauStatut,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_SERVEUR');

        // Mapping simple entre le texte de l'URL et l'Enum
        $statutEnum = match ($nouveauStatut) {
            'prete' => StatutCommande::prete,
            'servie' => StatutCommande::servie,
            default => null,
        };

        if (!$statutEnum) {
            $this->addFlash('danger', 'Statut invalide.');
            return $this->redirectToRoute('app_serveur_commandes_en_cours');
        }

        $commande->setStatus($statutEnum);

        // Si la commande est servie, on libère la table automatiquement
        if ($statutEnum === StatutCommande::servie) {
            $table = $commande->getTableCommande();
            if ($table) {
                $table->setEstlibre(true);
            }
        }

        $em->flush();

        $this->addFlash('success', 'La commande est désormais : ' . $statutEnum->value);
        return $this->redirectToRoute('app_serveur_commandes_en_cours');
    }
    // src/Controller/ServeurController.php

    #[Route('/table/{id}/toggle-statut', name: 'table_toggle_statut', methods: ['POST'])]
    public function toggleTableStatut(Table $table, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SERVEUR');

        // On inverse l'état actuel
        $nouvelEtat = !$table->isEstlibre();
        $table->setEstlibre($nouvelEtat);

        $em->flush();

        $message = $nouvelEtat ? 'La table n°' . $table->getNumero() . ' est maintenant LIBRE.'
            : 'La table n°' . $table->getNumero() . ' est maintenant OCCUPÉE.';

        $this->addFlash('info', $message);

        return $this->redirectToRoute('app_serveur_dashboard');
    }
}
