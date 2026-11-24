<?php

namespace App\Notifications;

use App\Enums\LeaveStatus;
use App\Models\Demande;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Tells an employee their request was accepted or refused: stored in the
 * database (the bell in the app) and sent by email.
 *
 * Sent immediately rather than queued: a queued notification never arrives
 * unless a queue worker is running. 'database' comes first so the in-app
 * notification is saved even if the mail server is unreachable.
 */
class DemandeStatusChanged extends Notification
{
    use Queueable;

    public function __construct(protected Demande $demande) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $accepted = $this->demande->etat === LeaveStatus::Accepted;

        $mail = (new MailMessage)
            ->subject($accepted ? 'Votre demande de congé a été acceptée' : 'Votre demande de congé a été refusée')
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->message())
            ->line('**Type :** '.$this->demande->type_conge->label())
            ->line('**Du :** '.$this->formatDate($this->demande->date_debut).' **au :** '.$this->formatDate($this->demande->date_fin))
            ->line("**Nombre de jours :** {$this->demande->nbr_jours}");

        if (filled($this->demande->comment)) {
            $mail->line("**Commentaire :** {$this->demande->comment}");
        }

        return $mail->action('Voir mes demandes', route('dashboard'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'demande_id' => $this->demande->id,
            'status' => $this->demande->etat->value,
            'message' => $this->message(),
            'date_debut' => $this->demande->date_debut,
            'date_fin' => $this->demande->date_fin,
            'nbr_jours' => $this->demande->nbr_jours,
            'comment' => $this->demande->comment,
            'type_conge' => $this->demande->type_conge->value,
        ];
    }

    private function message(): string
    {
        return $this->demande->etat === LeaveStatus::Accepted
            ? 'Votre demande de congé a été acceptée.'
            : 'Votre demande de congé a été refusée.';
    }

    private function formatDate(mixed $date): string
    {
        return Carbon::parse($date)->format('d/m/Y');
    }
}
