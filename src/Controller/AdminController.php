<?php
// src/Controller/AdminController.php

namespace App\Controller;

use App\Entity\Plat;
use App\Form\PlatType;
use App\Repository\PlatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;
use App\Form\UserType;
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
        $plats = $repo->findBy([], ['nom' => 'ASC']);

        return $this->render('admin/plats.html.twig', [
            'plats' => $plats,
        ]);
    }

    // ==================== AJOUTER UN PLAT ====================
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

    // ==================== MODIFIER UN PLAT ====================
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

    // ==================== SUPPRIMER UN PLAT ====================
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

    // ==================== STATISTIQUES ====================
    #[Route('/statistiques', name: 'statistiques')]
    public function statistiques(): Response
    {
        return $this->render('admin/statistiques.html.twig');
    }
    #[Route('/utilisateurs', name: 'utilisateurs')]
    public function utilisateurs(UserRepository $repo): Response
    {
        return $this->render('admin/utilisateurs.html.twig', [
            'users' => $repo->findAll(),
        ]);
    }
    // ==================== ajouter utilisateur ====================

    #[Route('/utilisateur/ajouter', name: 'ajouter_utilisateur')]
    public function ajouterUtilisateur(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = $form->get('roles')->getData();
            $user->setRoles([$role]); // on met dans un tableau

            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $plainPassword));

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès !');
            return $this->redirectToRoute('app_admin_utilisateurs');
        }

        return $this->render('admin/utilisateur_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
