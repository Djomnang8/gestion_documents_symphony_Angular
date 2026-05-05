<?php
namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;


#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateurs')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $nom = '';

    #[ORM\Column(length: 100)]
    private string $prenom = '';

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column(nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column]
    private string $motDePasseHash = '';

    #[ORM\Column]
    private bool $estActif = true;

    #[ORM\Column]
    private bool $estListeNoire = false;

    #[ORM\Column(nullable: true)]
    private ?string $motifListeNoire = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $derniereConnexion = null;

    #[ORM\Column(length: 50)]
    private string $typeUtilisateur = 'AGENT';

    #[ORM\Column]
    private bool $estSupprime = false;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Service $service = null;

    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'utilisateurs_roles')]
    private Collection $roles_;

    public function __construct()
    {
        $this->roles_ = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }
    public function getMotDePasseHash(): string { return $this->motDePasseHash; }
    public function setMotDePasseHash(string $hash): static { $this->motDePasseHash = $hash; return $this; }
    public function isEstActif(): bool { return $this->estActif; }
    public function setEstActif(bool $v): static { $this->estActif = $v; return $this; }
    public function isEstListeNoire(): bool { return $this->estListeNoire; }
    public function setEstListeNoire(bool $v): static { $this->estListeNoire = $v; return $this; }
    public function getMotifListeNoire(): ?string { return $this->motifListeNoire; }
    public function setMotifListeNoire(?string $v): static { $this->motifListeNoire = $v; return $this; }
    public function getDerniereConnexion(): ?\DateTimeImmutable { return $this->derniereConnexion; }
    public function setDerniereConnexion(?\DateTimeImmutable $v): static { $this->derniereConnexion = $v; return $this; }
    public function getTypeUtilisateur(): string { return $this->typeUtilisateur; }
    public function setTypeUtilisateur(string $v): static { $this->typeUtilisateur = $v; return $this; }
    public function isEstSupprime(): bool { return $this->estSupprime; }
    public function setEstSupprime(bool $v): static { $this->estSupprime = $v; return $this; }
    public function getService(): ?Service { return $this->service; }
    public function setService(?Service $s): static { $this->service = $s; return $this; }
    public function getRoles_(): Collection { return $this->roles_; }

    // UserInterface
    public function getUserIdentifier(): string { return $this->email; }
    public function getPassword(): string { return $this->motDePasseHash; }
    public function eraseCredentials(): void {}

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        foreach ($this->roles_ as $role) {
            $roles[] = 'ROLE_' . strtoupper($role->getNom());
        }
        return array_unique($roles);
    }

    public function getRoleNom(): string
    {
        foreach ($this->roles_ as $role) {
            return $role->getNom();
        }
        return $this->typeUtilisateur;
    }

    public function getPermissions(): array
    {
        $permissions = [];
        foreach ($this->roles_ as $role) {
            foreach ($role->getPermissions() as $permission) {
                $permissions[] = $permission->getNom();
            }
        }
        return array_unique($permissions);
    }
}