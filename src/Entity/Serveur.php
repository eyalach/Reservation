<?php

namespace App\Entity;

use App\Repository\ServeurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServeurRepository::class)]
class Serveur extends User
{

}
