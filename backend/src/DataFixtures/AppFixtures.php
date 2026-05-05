<?php
namespace App\DataFixtures;

use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\Service;
use App\Entity\StatutDossier;
use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        // ── Permissions ──────────────────────────────────────────────────
        $permsData = [
            'dossiers.voir', 'dossiers.creer', 'dossiers.modifier', 'dossiers.supprimer',
            'dossiers.valider', 'dossiers.rejeter',
            'archivage.archiver', 'archivage.restaurer',
            'utilisateurs.voir', 'utilisateurs.gerer',
            'statistiques.voir', 'journaux.voir', 'notifications.voir',
        ];
        $perms = [];
        foreach ($permsData as $nom) {
            $p = new Permission();
            $p->setNom($nom);
            $manager->persist($p);
            $perms[$nom] = $p;
        }

        // ── Rôles ────────────────────────────────────────────────────────
        $roleAdmin = new Role();
        $roleAdmin->setNom('Administrateur')->setDescription('Accès complet');
        foreach ($perms as $p) $roleAdmin->getPermissions()->add($p);
        $manager->persist($roleAdmin);

        $roleAgent = new Role();
        $roleAgent->setNom('Agent')->setDescription('Agent de traitement');
        foreach (['dossiers.voir','dossiers.creer','dossiers.modifier','dossiers.valider','dossiers.rejeter','statistiques.voir','notifications.voir'] as $n) {
            $roleAgent->getPermissions()->add($perms[$n]);
        }
        $manager->persist($roleAgent);

        $roleArchiviste = new Role();
        $roleArchiviste->setNom('Archiviste')->setDescription('Archiviste');
        foreach (['dossiers.voir','archivage.archiver','archivage.restaurer','statistiques.voir','notifications.voir'] as $n) {
            $roleArchiviste->getPermissions()->add($perms[$n]);
        }
        $manager->persist($roleArchiviste);

        // ── Services ─────────────────────────────────────────────────────
        $services = [];
        foreach (['État Civil', 'Urbanisme', 'Finances', 'Action Sociale', 'Éducation'] as $nom) {
            $s = new Service();
            $s->setNom($nom)->setEstActif(true);
            $manager->persist($s);
            $services[] = $s;
        }

        // ── Statuts dossier ───────────────────────────────────────────────
        // OBLIGATOIRES : RECU, EN_COURS, TRANSFERE, REJETE, TERMINE, ARCHIVE
        $statutsData = [
            ['RECU',      'Reçu'],
            ['EN_COURS',  'En cours de traitement'],
            ['TRANSFERE', 'Transféré'],
            ['REJETE',    'Rejeté'],
            ['TERMINE',   'Terminé'],
            ['ARCHIVE',   'Archivé'],
        ];
        foreach ($statutsData as [$code, $libelle]) {
            $s = new StatutDossier();
            $s->setCode($code)->setLibelle($libelle);
            $manager->persist($s);
        }

        // ── Utilisateurs ──────────────────────────────────────────────────
        $admin = new Utilisateur();
        $admin->setNom('Admin')->setPrenom('Système')
              ->setEmail('admin@symphony.cm')
              ->setTypeUtilisateur('Administrateur')
              ->setMotDePasseHash($this->hasher->hashPassword($admin, 'Admin1234!'));
        $admin->getRoles_()->add($roleAdmin);
        $manager->persist($admin);

        $agent = new Utilisateur();
        $agent->setNom('Dupont')->setPrenom('Jean')
              ->setEmail('agent@symphony.cm')
              ->setTypeUtilisateur('Agent')
              ->setService($services[0])
              ->setMotDePasseHash($this->hasher->hashPassword($agent, 'Agent1234!'));
        $agent->getRoles_()->add($roleAgent);
        $manager->persist($agent);

        $archiviste = new Utilisateur();
        $archiviste->setNom('Martin')->setPrenom('Sophie')
                   ->setEmail('archiviste@symphony.cm')
                   ->setTypeUtilisateur('Archiviste')
                   ->setMotDePasseHash($this->hasher->hashPassword($archiviste, 'Archiv1234!'));
        $archiviste->getRoles_()->add($roleArchiviste);
        $manager->persist($archiviste);

        $manager->flush();
    }
}
