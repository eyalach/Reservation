<?php

namespace App\Entity;

use App\Enum\StatutCommande;
use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    #[ORM\Column(type: 'string', enumType: StatutCommande::class)]
    private StatutCommande  $status=StatutCommande::en_cours;

    #[ORM\Column]
    private ?\DateTime $date = null;

    #[ORM\Column]
    private ?float $total = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    private ?Table $tableCommande = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    private ?Paiement $paiementCommande = null;

    /**
     * @var Collection<int, LigneCommande>
     */
    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: LigneCommande::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $ligneCommandes;

    public function __construct()
    {
        $this->ligneCommandes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(float $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getStatus(): StatutCommande
    {
        return $this->status;
    }

    public function setStatus(StatutCommande $status): void
    {
        $this->status = $status;
    }

    public function getTableCommande(): ?Table
    {
        return $this->tableCommande;
    }

    public function setTableCommande(?Table $tableCommande): static
    {
        $this->tableCommande = $tableCommande;

        return $this;
    }

    public function getPaiementCommande(): ?Paiement
    {
        return $this->paiementCommande;
    }

    public function setPaiementCommande(?Paiement $paiementCommande): static
    {
        $this->paiementCommande = $paiementCommande;

        return $this;
    }

    /**
     * @return Collection<int, LigneCommande>
     */
    public function getLigneCommandes(): Collection
    {
        return $this->ligneCommandes;
    }

    public function addLigneCommande(LigneCommande $ligneCommande): static
    {
        if (!$this->ligneCommandes->contains($ligneCommande)) {
            $this->ligneCommandes->add($ligneCommande);
            $ligneCommande->setCommande($this);
        }

        return $this;
    }

    public function removeLigneCommande(LigneCommande $ligneCommande): static
    {
        if ($this->ligneCommandes->removeElement($ligneCommande)) {
            // set the owning side to null (unless already changed)
            if ($ligneCommande->getCommande() === $this) {
                $ligneCommande->setCommande(null);
            }
        }

        return $this;
    }


    #[ORM\Column(length: 50)]
    private string $modePaiement = 'Espèces';

    public function getModePaiement(): string { return $this->modePaiement; }
    public function setModePaiement(string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $client = null;

    public function getClient(): ?User { return $this->client; }
    public function setClient(?User $client): static
    {
        $this->client = $client;
        return $this;
    }


}
