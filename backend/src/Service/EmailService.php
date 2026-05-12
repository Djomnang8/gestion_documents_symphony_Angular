<?php
namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail = 'joycedjomnang@gmail.com',
        private string $fromName  = 'Gestion Documents'
    ) {}

    /**
     * Email envoyé au citoyen après dépôt de son dossier
     */
    public function envoyerConfirmationDepot(
        string $emailCitoyen,
        string $nomCitoyen,
        string $numeroDossier,
        string $titreDossier
    ): void {
        $email = (new Email())
            ->from("$this->fromName <$this->fromEmail>")
            ->to($emailCitoyen)
            ->subject("✅ Confirmation de dépôt — Dossier $numeroDossier")
            ->html($this->templateConfirmationDepot(
                $nomCitoyen, $numeroDossier, $titreDossier
            ));

        $this->mailer->send($email);
    }

    public function envoyerConfirmationDepotMultiple(
        string $emailCitoyen,
        string $nomCitoyen,
        array $numerosDossiers,
        string $titreDossier
    ): void {
        if (count($numerosDossiers) <= 1) {
            $this->envoyerConfirmationDepot($emailCitoyen, $nomCitoyen, $numerosDossiers[0] ?? '', $titreDossier);
            return;
        }

        $listeNumeros = '<ul>' . implode('', array_map(fn(string $numero) => '<li><strong>' . htmlspecialchars($numero, ENT_QUOTES, 'UTF-8') . '</strong></li>', $numerosDossiers)) . '</ul>';
        $email = (new Email())
            ->from("$this->fromName <$this->fromEmail>")
            ->to($emailCitoyen)
            ->subject('✅ Confirmation de dépôt — ' . count($numerosDossiers) . ' dossiers reçus')
            ->html($this->templateConfirmationDepotMultiple($nomCitoyen, $listeNumeros, $titreDossier));

        $this->mailer->send($email);
    }

    private function templateConfirmationDepotMultiple(string $nom, string $listeNumeros, string $titre): string
    {
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head><meta charset='UTF-8'><title>Confirmation de dépôt</title></head>
        <body style='font-family: Arial, sans-serif; background:#f4f4f4; padding:20px;'>
          <div style='max-width:600px; margin:auto; background:#fff; border-radius:8px; overflow:hidden;'>
            <div style='background:#1e3a5f; padding:26px; text-align:center;'>
              <h1 style='color:#fff; margin:0; font-size:1.4rem;'>🏛️ Gestion des Documents</h1>
            </div>
            <div style='padding:30px;'>
              <h2 style='color:#27ae60;'>✅ Vos dossiers ont bien été reçus</h2>
              <p>Bonjour <strong>$nom</strong>,</p>
              <p>Nous confirmons la réception de vos dossiers pour : <strong>$titre</strong>.</p>
              <div style='background:#f0f4ff; border-left:4px solid #1e3a5f; padding:16px; border-radius:4px; margin:20px 0;'>
                <p style='margin-top:0;'><strong>Numéros de suivi :</strong></p>
                $listeNumeros
              </div>
              <p>📌 Conservez ces numéros pour suivre chaque dossier séparément.</p>
              <p>Cordialement,<br><strong>L'équipe de Gestion des Documents</strong></p>
            </div>
          </div>
        </body></html>";
    }

    /**
     * Email envoyé au citoyen quand le statut de son dossier change
     */
    public function envoyerNotificationStatut(
        string $emailCitoyen,
        string $nomCitoyen,
        string $numeroDossier,
        string $nouveauStatut,
        ?string $commentaire = null
    ): void {
        $email = (new Email())
            ->from("$this->fromName <$this->fromEmail>")
            ->to($emailCitoyen)
            ->subject("📋 Mise à jour de votre dossier $numeroDossier — $nouveauStatut")
            ->html($this->templateNotificationStatut(
                $nomCitoyen, $numeroDossier, $nouveauStatut, $commentaire
            ));

        $this->mailer->send($email);
    }

    /**
     * Email dédié au citoyen quand son dossier est rejeté (avec motif obligatoire)
     */
    public function envoyerNotificationRejet(
        string $emailCitoyen,
        string $nomCitoyen,
        string $numeroDossier,
        string $titreDossier,
        ?string $motifRejet = null
    ): void {
        $email = (new Email())
            ->from("$this->fromName <$this->fromEmail>")
            ->to($emailCitoyen)
            ->subject("❌ Dossier $numeroDossier rejeté — Action requise")
            ->html($this->templateRejet(
                $nomCitoyen, $numeroDossier, $titreDossier, $motifRejet
            ));

        $this->mailer->send($email);
    }

    // ── TEMPLATES HTML ────────────────────────────────────────────────────

    private function templateConfirmationDepot(
        string $nom,
        string $numero,
        string $titre
    ): string {
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head><meta charset='UTF-8'><title>Confirmation de dépôt</title></head>
        <body style='font-family: Arial, sans-serif; background:#f4f4f4; padding:20px;'>
          <div style='max-width:600px; margin:auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1);'>

            <div style='background:#1e3a5f; padding:30px; text-align:center;'>
              <h1 style='color:#fff; margin:0; font-size:1.5rem;'>🏛️ Gestion des Documents</h1>
            </div>

            <div style='padding:30px;'>
              <h2 style='color:#27ae60;'>✅ Votre dossier a bien été reçu par nos agents</h2>
              <p>Bonjour <strong>$nom</strong>,</p>
              <p>Nous confirmons la réception et la prise en charge de votre dossier. Voici le récapitulatif :</p>

              <div style='background:#f0f4ff; border-left:4px solid #1e3a5f; padding:16px; border-radius:4px; margin:20px 0;'>
                <p style='margin:4px 0;'><strong>Numéro de suivi :</strong>
                  <span style='font-size:1.2rem; color:#1e3a5f; font-weight:bold;'> $numero</span>
                </p>
                <p style='margin:4px 0;'><strong>Objet :</strong> $titre</p>
              </div>

              <p>📌 Conservez bien ce numéro de suivi. Il vous permettra de suivre l'avancement de votre dossier à tout moment.</p>

              <div style='background:#fff3cd; border:1px solid #ffc107; border-radius:4px; padding:12px; margin:20px 0;'>
                <p style='margin:0;'>⏱️ <strong>Délai de traitement estimé :</strong> 7 jours ouvrables</p>
              </div>

              <p>Vous recevrez un email à chaque changement de statut de votre dossier.</p>
              <p>Cordialement,<br><strong>L'équipe de Gestion des Documents</strong></p>
            </div>

            <div style='background:#f4f4f4; padding:16px; text-align:center; font-size:0.8rem; color:#999;'>
              Cet email est automatique, merci de ne pas y répondre.
            </div>
          </div>
        </body>
        </html>";
    }

    private function templateNotificationStatut(
        string $nom,
        string $numero,
        string $statut,
        ?string $commentaire
    ): string {
        $couleur = match(strtoupper($statut)) {
            'EN COURS'  => '#e67e22',
            'TERMINÉ'   => '#27ae60',
            'REJETÉ'    => '#e74c3c',
            'TRANSFÉRÉ' => '#8e44ad',
            'REÇU'      => '#1e3a5f',
            default     => '#1e3a5f',
        };

        $commentaireHtml = $commentaire
            ? "<div style='background:#fff3cd; border:1px solid #ffc107; border-radius:4px; padding:12px; margin:16px 0;'>
                 <p style='margin:0;'><strong>Commentaire :</strong> $commentaire</p>
               </div>"
            : '';

        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head><meta charset='UTF-8'><title>Mise à jour dossier</title></head>
        <body style='font-family: Arial, sans-serif; background:#f4f4f4; padding:20px;'>
          <div style='max-width:600px; margin:auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1);'>

            <div style='background:#1e3a5f; padding:30px; text-align:center;'>
              <h1 style='color:#fff; margin:0; font-size:1.5rem;'>🏛️ Gestion des Documents</h1>
            </div>

            <div style='padding:30px;'>
              <h2 style='color:#1e3a5f;'>📋 Mise à jour de votre dossier</h2>
              <p>Bonjour <strong>$nom</strong>,</p>
              <p>Le statut de votre dossier <strong>$numero</strong> a été mis à jour :</p>

              <div style='text-align:center; margin:24px 0;'>
                <span style='background:$couleur; color:#fff; padding:10px 24px; border-radius:20px; font-size:1.1rem; font-weight:bold;'>
                  $statut
                </span>
              </div>

              $commentaireHtml

              <p>Cordialement,<br><strong>L'équipe de Gestion des Documents</strong></p>
            </div>

            <div style='background:#f4f4f4; padding:16px; text-align:center; font-size:0.8rem; color:#999;'>
              Cet email est automatique, merci de ne pas y répondre.
            </div>
          </div>
        </body>
        </html>";
    }

    private function templateRejet(
        string $nom,
        string $numero,
        string $titre,
        ?string $motif
    ): string {
        $motifHtml = $motif
            ? "<div style='background:#fdecea; border:2px solid #e74c3c; border-radius:6px; padding:16px; margin:20px 0;'>
                 <p style='margin:0 0 8px 0; font-weight:bold; color:#c0392b;'>📋 Motif du rejet :</p>
                 <p style='margin:0; color:#333;'>$motif</p>
               </div>"
            : "<div style='background:#fdecea; border:2px solid #e74c3c; border-radius:6px; padding:16px; margin:20px 0;'>
                 <p style='margin:0; color:#c0392b;'>Aucun motif précisé. Veuillez contacter le service concerné pour plus d'informations.</p>
               </div>";

        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head><meta charset='UTF-8'><title>Dossier rejeté</title></head>
        <body style='font-family: Arial, sans-serif; background:#f4f4f4; padding:20px;'>
          <div style='max-width:600px; margin:auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1);'>

            <div style='background:#c0392b; padding:30px; text-align:center;'>
              <h1 style='color:#fff; margin:0; font-size:1.5rem;'>🏛️ Gestion des Documents</h1>
            </div>

            <div style='padding:30px;'>
              <h2 style='color:#c0392b;'>❌ Votre dossier a été rejeté</h2>
              <p>Bonjour <strong>$nom</strong>,</p>
              <p>Nous vous informons que votre dossier <strong>$numero</strong> — <em>$titre</em> — a été <strong style='color:#c0392b;'>rejeté</strong> par nos agents.</p>

              $motifHtml

              <div style='background:#f0f4ff; border-left:4px solid #1e3a5f; padding:14px; border-radius:4px; margin:20px 0;'>
                <p style='margin:0;'>💡 <strong>Que faire ?</strong> Vous pouvez déposer un nouveau dossier en tenant compte du motif indiqué ci-dessus, ou contacter directement le service compétent pour obtenir des éclaircissements.</p>
              </div>

              <p>Nous vous remercions de votre compréhension.</p>
              <p>Cordialement,<br><strong>L'équipe de Gestion des Documents</strong></p>
            </div>

            <div style='background:#f4f4f4; padding:16px; text-align:center; font-size:0.8rem; color:#999;'>
              Cet email est automatique, merci de ne pas y répondre.
            </div>
          </div>
        </body>
        </html>";
    }
}
