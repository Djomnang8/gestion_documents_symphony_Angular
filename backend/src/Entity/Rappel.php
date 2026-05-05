<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rappels')]
class Rappel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: Dossier::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Dossier $dossier = null;

    #[ORM\Column(length: 255)]
    private string $titre = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $objet = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, options: ['default' => 'RAPPEL'])]
    private string $type = 'RAPPEL';

    #[ORM\Column(length: 20, options: ['default' => 'ENVOYE'])]
    private string $statut = 'ENVOYE';

    #[ORM\Column]
    private \DateTimeImmutable $dateRappel;

    #[ORM\Column]
    private \DateTimeImmutable $dateEnvoi;

    #[ORM\Column]
    private bool $estEffectue = false;

    #[ORM\Column(nullable: true)]
    private ?int $tentatives = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $erreur = null;

    public function __construct()
    {
        $this->dateRappel = new \DateTimeImmutable();
        $this->dateEnvoi  = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $u): static { $this->utilisateur = $u; return $this; }
    public function getDossier(): ?Dossier { return $this->dossier; }
    public function setDossier(?Dossier $d): static { $this->dossier = $d; return $this; }
    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $t): static { $this->titre = $t; return $this; }
    public function getObjet(): ?string { return $this->objet; }
    public function setObjet(?string $o): static { $this->objet = $o; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $t): static { $this->type = $t; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): static { $this->statut = $s; return $this; }
    public function getDateRappel(): \DateTimeImmutable { return $this->dateRappel; }
    public function setDateRappel(\DateTimeImmutable $d): static { $this->dateRappel = $d; return $this; }
    public function getDateEnvoi(): \DateTimeImmutable { return $this->dateEnvoi; }
    public function setDateEnvoi(\DateTimeImmutable $d): static { $this->dateEnvoi = $d; return $this; }
    public function isEstEffectue(): bool { return $this->estEffectue; }
    public function setEstEffectue(bool $v): static { $this->estEffectue = $v; return $this; }
    public function getTentatives(): ?int { return $this->tentatives; }
    public function setTentatives(?int $t): static { $this->tentatives = $t; return $this; }
    public function getErreur(): ?string { return $this->erreur; }
    public function setErreur(?string $e): static { $this->erreur = $e; return $this; }
}
