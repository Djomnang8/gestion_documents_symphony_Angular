<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'historique_statuts')]
class HistoriqueStatut
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Dossier::class, inversedBy: 'historiqueStatuts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Dossier $dossier;

    #[ORM\ManyToOne(targetEntity: StatutDossier::class)]
    private ?StatutDossier $ancienStatut = null;

    #[ORM\ManyToOne(targetEntity: StatutDossier::class)]
    #[ORM\JoinColumn(nullable: false)]
    private StatutDossier $nouveauStatut;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private \DateTimeImmutable $dateChangement;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $agent = null;

    public function __construct()
    {
        $this->dateChangement = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getDossier(): Dossier { return $this->dossier; }
    public function setDossier(Dossier $d): static { $this->dossier = $d; return $this; }
    public function getAncienStatut(): ?StatutDossier { return $this->ancienStatut; }
    public function setAncienStatut(?StatutDossier $s): static { $this->ancienStatut = $s; return $this; }
    public function getNouveauStatut(): StatutDossier { return $this->nouveauStatut; }
    public function setNouveauStatut(StatutDossier $s): static { $this->nouveauStatut = $s; return $this; }
    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $c): static { $this->commentaire = $c; return $this; }
    public function getDateChangement(): \DateTimeImmutable { return $this->dateChangement; }
    public function getAgent(): ?Utilisateur { return $this->agent; }
    public function setAgent(?Utilisateur $a): static { $this->agent = $a; return $this; }
}