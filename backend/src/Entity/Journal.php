<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'journaux')]
class Journal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(length: 100)]
    private string $module = '';

    #[ORM\Column(length: 100)]
    private string $action = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    #[ORM\Column]
    private int $niveauId = 1;

    #[ORM\Column]
    private \DateTimeImmutable $dateAction;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $entiteId = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $adresseIp = null;

    public function __construct()
    {
        $this->dateAction = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $u): static { $this->utilisateur = $u; return $this; }
    public function getModule(): string { return $this->module; }
    public function setModule(string $m): static { $this->module = $m; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $a): static { $this->action = $a; return $this; }
    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $d): static { $this->details = $d; return $this; }
    public function getNiveauId(): int { return $this->niveauId; }
    public function setNiveauId(int $n): static { $this->niveauId = $n; return $this; }
    public function getDateAction(): \DateTimeImmutable { return $this->dateAction; }
    public function getEntiteId(): ?string { return $this->entiteId; }
    public function setEntiteId(?string $e): static { $this->entiteId = $e; return $this; }
    public function getAdresseIp(): ?string { return $this->adresseIp; }
    public function setAdresseIp(?string $ip): static { $this->adresseIp = $ip; return $this; }
}