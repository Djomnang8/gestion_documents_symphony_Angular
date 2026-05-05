<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'versions_document')]
class VersionDocument
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Dossier::class, inversedBy: 'versionsDocument')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Dossier $dossier;

    #[ORM\Column]
    private int $numeroVersion = 1;

    #[ORM\Column(length: 255)]
    private string $nomFichier = '';

    #[ORM\Column(length: 500)]
    private string $cheminFichier = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $typeFichier = null;

    #[ORM\Column(nullable: true)]
    private ?int $tailleFichier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $empreinteHash = null;

    #[ORM\Column]
    private \DateTimeImmutable $dateCreation;

    #[ORM\Column]
    private bool $estActive = true;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    public function __construct()
    {
        $this->id = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getDossier(): Dossier { return $this->dossier; }
    public function setDossier(Dossier $d): static { $this->dossier = $d; return $this; }
    public function getNumeroVersion(): int { return $this->numeroVersion; }
    public function setNumeroVersion(int $n): static { $this->numeroVersion = $n; return $this; }
    public function getNomFichier(): string { return $this->nomFichier; }
    public function setNomFichier(string $n): static { $this->nomFichier = $n; return $this; }
    public function getCheminFichier(): string { return $this->cheminFichier; }
    public function setCheminFichier(string $c): static { $this->cheminFichier = $c; return $this; }
    public function getTypeFichier(): ?string { return $this->typeFichier; }
    public function setTypeFichier(?string $t): static { $this->typeFichier = $t; return $this; }
    public function getTailleFichier(): ?int { return $this->tailleFichier; }
    public function setTailleFichier(?int $t): static { $this->tailleFichier = $t; return $this; }
    public function getEmpreinteHash(): ?string { return $this->empreinteHash; }
    public function setEmpreinteHash(?string $h): static { $this->empreinteHash = $h; return $this; }
    public function getDateCreation(): \DateTimeImmutable { return $this->dateCreation; }
    public function isEstActive(): bool { return $this->estActive; }
    public function setEstActive(bool $v): static { $this->estActive = $v; return $this; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $u): static { $this->utilisateur = $u; return $this; }
    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $c): static { $this->commentaire = $c; return $this; }
}