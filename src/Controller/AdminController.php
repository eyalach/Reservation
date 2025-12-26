<?php
// src/Controller/AdminController.php

namespace App\Controller;

use App\Entity\Categorie;
use App\Form\CategorieType;
use App\Repository\CategorieRepository;
use App\Entity\Plat;
use App\Form\PlatType;
use App\Repository\PlatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;
use App\Form\UserType;
use App\Entity\Table;
use App\Form\TableType;
use App\Repository\TableRepository;

#[Route('/admin', name: 'app_admin_')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    // ==================== LISTE DES PLATS ====================
    #[Route('/plats', name: 'plats')]
    public function plats(PlatRepository $repo): Response
    {
        // Récupération de tous les plats triés par nom
        $plats = $repo->findBy([], ['nom' => 'ASC']);

        // Regroupement par catégorie
        $platsParCategorie = [];
        foreach ($plats as $plat) {
            $nomCat = $plat->getCategorie() ? $plat->getCategorie()->getNom() : 'Sans catégorie';
            $platsParCategorie[$nomCat][] = $plat;
        }

        // Ordre fixe des catégories (celles que tu veux afficher en premier)
        $ordreCategories = [
            "Entrées / Antipasti",
            "Pâtes et Plats Principaux",
            "Pizzas (classiques)",
            "Desserts",
            "Boissons"
        ];

        // Création du tableau ordonné (catégories dans l'ordre voulu, même si vides)
        $platsParCategorieOrdonnes = [];
        foreach ($ordreCategories as $cat) {
            $platsParCategorieOrdonnes[$cat] = $platsParCategorie[$cat] ?? [];
        }

        // Ajout des autres catégories éventuelles à la fin
        foreach ($platsParCategorie as $cat => $liste) {
            if (!in_array($cat, $ordreCategories)) {
                $platsParCategorieOrdonnes[$cat] = $liste;
            }
        }

        return $this->render('admin/plats.html.twig', [
            'platsParCategorie' => $platsParCategorieOrdonnes,
        ]);
    }

    #[Route('/plat/ajouter', name: 'ajouter_plat')]
    public function ajouterPlat(Request $request, EntityManagerInterface $em): Response
    {
        $plat = new Plat();
        $plat->setDisponible(true);
        $form = $this->createForm(PlatType::class, $plat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($plat);
            $em->flush();
            $this->addFlash('success', 'Plat ajouté avec succès !');
            return $this->redirectToRoute('app_admin_plats');
        }

        return $this->render('admin/plats_form.html.twig', [
            'plat' => $plat,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/plat/{id}/modifier', name: 'modifier_plat')]
    public function modifierPlat(Plat $plat, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PlatType::class, $plat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Plat modifié avec succès !');
            return $this->redirectToRoute('app_admin_plats');
        }

        return $this->render('admin/plats_form.html.twig', [
            'plat' => $plat,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/plat/{id}/supprimer', name: 'supprimer_plat', methods: ['POST'])]
    public function supprimerPlat(Plat $plat, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$plat->getId(), $request->request->get('_token'))) {
            $em->remove($plat);
            $em->flush();
            $this->addFlash('danger', 'Plat supprimé.');
        }

        return $this->redirectToRoute('app_admin_plats');
    }

    #[Route('/statistiques', name: 'statistiques')]
    public function statistiques(): Response
    {
        return $this->render('admin/statistiques.html.twig');
    }
    // ==================== LISTE DES utilisateurs====================

    #[Route('/utilisateurs', name: 'utilisateurs')]
    public function utilisateurs(UserRepository $repo): Response
    {
        return $this->render('admin/utilisateurs.html.twig', [
            'users' => $repo->findAll(),
        ]);
    }

    #[Route('/utilisateur/ajouter', name: 'ajouter_utilisateur')]
    public function ajouterUtilisateur(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles($form->get('roles')->getData());
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $plainPassword));
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur créé avec succès !');
            return $this->redirectToRoute('app_admin_utilisateurs');
        }
        return $this->render('admin/utilisateur_form.html.twig', ['form' => $form->createView()]);
    }
    #[Route('/utilisateur/{id}/modifier', name: 'modifier_utilisateur')]
    public function modifierUtilisateur(User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        // On utilise le même formulaire UserType
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion du mot de passe si rempli
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
            }

            $em->flush();
            $this->addFlash('success', 'Utilisateur mis à jour avec succès !');
            return $this->redirectToRoute('app_admin_utilisateurs');
        }

        return $this->render('admin/utilisateur_form.html.twig', [
            'form' => $form->createView(),
            'editMode' => true,
            'user' => $user
        ]);
    }

    #[Route('/utilisateur/{id}/supprimer', name: 'supprimer_utilisateur', methods: ['POST'])]
    public function supprimerUtilisateur(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            // Sécurité : éviter de se supprimer soi-même
            if ($user === $this->getUser()) {
                $this->addFlash('danger', 'Action impossible : suppression de votre propre compte.');
            } else {
                $em->remove($user);
                $em->flush();
                $this->addFlash('danger', 'Utilisateur supprimé.');
            }
        }
        return $this->redirectToRoute('app_admin_utilisateurs');
    }

    // ==================== CATEGORIES ====================
    #[Route('/categories', name: 'categories')] // Deviendra 'app_admin_categories'
    public function listeCategories(CategorieRepository $repo): Response
    {
        return $this->render('admin/categorie/index.html.twig', [
            'categories' => $repo->findAll(),
        ]);
    }

    #[Route('/categories/ajouter', name: 'categorie_ajouter')]
    #[Route('/categories/modifier/{id}', name: 'categorie_modifier')]
    public function formCategorie(Categorie $categorie = null, Request $request, EntityManagerInterface $em): Response
    {
        if (!$categorie) { $categorie = new Categorie(); }
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($categorie);
            $em->flush();
            $this->addFlash('success', 'Catégorie enregistrée !');
            return $this->redirectToRoute('app_admin_categories');
        }

        return $this->render('admin/categorie/form.html.twig', [
            'form' => $form->createView(),
            'editMode' => $categorie->getId() !== null
        ]);
    }
    // ==================== GESTION DES TABLES ====================

    #[Route('/tables', name: 'table_index')]
    public function tables(TableRepository $repo): Response
    {
        return $this->render('admin/tables.html.twig', [
            'tables' => $repo->findAll(),
        ]);
    }

    #[Route('/table/new', name: 'table_new')]
    public function newTable(Request $request, EntityManagerInterface $em): Response
    {
        $table = new Table();
        $table->setEstlibre(true); // Par défaut libre

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($table);
            $em->flush();

            $this->addFlash('success', 'La table a été créée.');
            return $this->redirectToRoute('app_admin_table_index');
        }

        return $this->render('admin/table_new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/table/{id}/edit', name: 'table_edit')]
    public function editTable(Table $table, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'La table a été mise à jour.');
            return $this->redirectToRoute('app_admin_table_index');
        }

        return $this->render('admin/table_edit.html.twig', [
            'table' => $table,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/table/{id}/delete', name: 'table_delete', methods: ['POST'])]
    public function deleteTable(Table $table, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $table->getId(), $request->request->get('_token'))) {
            $em->remove($table);
            $em->flush();
            $this->addFlash('danger', 'Table supprimée.');
        }

        return $this->redirectToRoute('app_admin_table_index');
    }
}
