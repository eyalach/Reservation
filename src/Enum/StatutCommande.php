<?php


namespace App\Enum;

enum StatutCommande: string
{
    case en_cours = 'EN_COURS';
    case prete = 'PRETE';
    case servie = 'SERVIE';
    case paye = 'PAYE';
}
