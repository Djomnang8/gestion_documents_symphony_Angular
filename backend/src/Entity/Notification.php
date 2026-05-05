<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private string $titre = '';

    #[ORM\Column(type: 'text')]
    private string $message = '';

    #[ORM\Column(length: 20, options: ['default' => 'INFO'])]
    private string $type = 'INFO';

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $dossierId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroDossier = null;

    #[ORM\Column]
    private bool $estLue = false;

    #[ORM\Column]
    private bool $estSupprimee = false;

    #[ORM\Column]
    private \DateTimeImmutable $dateCreation;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $u): static { $this->utilisateur = $u; return $this; }
    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $t): static { $this->titre = $t; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $m): static { $this->message = $m; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $t): static { $this->type = $t; return $this; }
    public function getDossierId(): ?string { return $this->dossierId; }
    public function setDossierId(?string $d): static { $this->dossierId = $d; return $this; }
    public function getNumeroDossier(): ?string { return $this->numeroDossier; }
    public function setNumeroDossier(?string $n): static { $this->numeroDossier = $n; return $this; }
    public function isEstLue(): bool { return $this->estLue; }
    public function setEstLue(bool $v): static { $this->estLue = $v; return $this; }
    public function isEstSupprimee(): bool { return $this->estSupprimee; }
    public function setEstSupprimee(bool $v): static { $this->estSupprimee = $v; return $this; }
    public function getDateCreation(): \DateTimeImmutable { return $this->dateCreation; }
}