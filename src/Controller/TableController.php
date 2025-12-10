<?php
// src/Controller/TableController.php

namespace App\Controller;

use App\Entity\Table;
use App\Form\TableType;
use App\Repository\TableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/table', name: 'app_admin_table_')]
class TableController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(TableRepository $tableRepository): Response
    {
        return $this->render('admin/tables.html.twig', [
            'tables' => $tableRepository->findBy([], ['numero' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $table = new Table();
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($table);
            $entityManager->flush();

            $this->addFlash('success', 'Table ajoutée avec succès !');
            return $this->redirectToRoute('app_admin_table_index');
        }

        return $this->render('admin/table_new.html.twig', [
            'table' => $table,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Table $table, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Table modifiée avec succès !');
            return $this->redirectToRoute('app_admin_table_index');
        }

        return $this->render('admin/table_edit.html.twig', [
            'table' => $table,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Table $table, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$table->getId(), $request->request->get('_token'))) {
            $entityManager->remove($table);
            $entityManager->flush();
            $this->addFlash('danger', 'Table supprimée.');
        }

        return $this->redirectToRoute('app_admin_table_index');
    }
}
