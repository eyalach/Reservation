<?php

namespace App\Repository;

use App\Entity\Commande;
use App\Enum\StatutCommande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * Retourne les commandes qui ne sont pas encore terminées (non servies et non payées)
     */
    public function findByStatutNonTermine(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status NOT IN (:statutsTermines)')
            ->setParameter('statutsTermines', [
                StatutCommande::servie->value,  // 'SERVIE'
                StatutCommande::paye->value,    // 'PAYE'
            ])
            ->orderBy('c.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
