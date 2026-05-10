<?php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dossiers')]
class Dossier
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(length: 50, unique: true)]
    private string $numero = '';

    #[ORM\Column(length: 255)]
    private string $titre = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private string $nomCitoyen = '';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $emailCitoyen = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephoneCitoyen = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motifRejet = null;

    #[ORM\Column]
    private \DateTimeImmutable $dateDepot;

    #[ORM\Column]
    private \DateTimeImmutable $dateMiseAJourStatut;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateArchivage = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $agent = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Service $service;

    #[ORM\ManyToOne(targetEntity: StatutDossier::class)]
    #[ORM\JoinColumn(nullable: false)]
    private StatutDossier $statut;

    #[ORM\OneToMany(mappedBy: 'dossier', targetEntity: VersionDocument::class, cascade: ['persist', 'remove'])]
    private Collection $versionsDocument;

    #[ORM\OneToMany(mappedBy: 'dossier', targetEntity: HistoriqueStatut::class, cascade: ['persist', 'remove'])]
    private Collection $historiqueStatuts;

    public function __construct()
    {
        // UUID v4 sans dépendance symfony/uid
        $this->id = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $this->dateDepot = new \DateTimeImmutable();
        $this->dateMiseAJourStatut = new \DateTimeImmutable();
        $this->versionsDocument = new ArrayCollection();
        $this->historiqueStatuts = new ArrayCollection();
    }

    public function getId(): string { return $this->id; }
    public function getNumero(): string { return $this->numero; }
    public function setNumero(string $n): static { $this->numero = $n; return $this; }
    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $t): static { $this->titre = $t; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }
    public function getNomCitoyen(): string { return $this->nomCitoyen; }
    public function setNomCitoyen(string $n): static { $this->nomCitoyen = $n; return $this; }
    public function getEmailCitoyen(): ?string { return $this->emailCitoyen; }
    public function setEmailCitoyen(?string $e): static { $this->emailCitoyen = $e; return $this; }
    public function getTelephoneCitoyen(): ?string { return $this->telephoneCitoyen; }
    public function setTelephoneCitoyen(?string $t): static { $this->telephoneCitoyen = $t; return $this; }
    public function getMotifRejet(): ?string { return $this->motifRejet; }
    public function setMotifRejet(?string $m): static { $this->motifRejet = $m; return $this; }
    public function getDateDepot(): \DateTimeImmutable { return $this->dateDepot; }
    public function setDateDepot(\DateTimeImmutable $d): static { $this->dateDepot = $d; return $this; }
    public function getDateMiseAJourStatut(): \DateTimeImmutable { return $this->dateMiseAJourStatut; }
    public function setDateMiseAJourStatut(\DateTimeImmutable $d): static { $this->dateMiseAJourStatut = $d; return $this; }
    public function getDateArchivage(): ?\DateTimeImmutable { return $this->dateArchivage; }
    public function setDateArchivage(?\DateTimeImmutable $d): static { $this->dateArchivage = $d; return $this; }
    public function getAgent(): ?Utilisateur { return $this->agent; }
    public function setAgent(?Utilisateur $a): static { $this->agent = $a; return $this; }
    public function getService(): Service { return $this->service; }
    public function setService(Service $s): static { $this->service = $s; return $this; }
    public function getStatut(): StatutDossier { return $this->statut; }
    public function setStatut(StatutDossier $s): static { $this->statut = $s; return $this; }
    public function getVersionsDocument(): Collection { return $this->versionsDocument; }
    public function getHistoriqueStatuts(): Collection { return $this->historiqueStatuts; }
}