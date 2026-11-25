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

    /**
     * @param  bool  $cancellationKept  The admin turned down the employee's cancellation request.
     * @param  bool  $byManager  The employee's manager refused the request (first approval step).
     */
    public function __construct(
        protected Demande $demande,
        protected bool $cancellationKept = false,
        protected bool $byManager = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->message())
            ->line('**Type :** '.$this->demande->type_conge->label())
            ->line('**Du :** '.$this->formatDate($this->demande->date_debut).($this->demande->start_half ? ' (après-midi)' : '')
                .' **au :** '.$this->formatDate($this->demande->date_fin).($this->demande->end_half ? ' (midi)' : ''))
            ->line('**Nombre de jours :** '.str_replace('.', ',', (string) (float) $this->demande->nbr_jours));

        if (filled($this->demande->comment)) {
            $mail->line("**Commentaire :** {$this->demande->comment}");
        }

        if ($this->byManager && filled($this->demande->manager_comment)) {
            $mail->line("**Motif du responsable :** {$this->demande->manager_comment}");
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
            // The bell shows one comment: the manager's reason when they refused.
            'comment' => $this->byManager && filled($this->demande->manager_comment)
                ? "Motif : {$this->demande->manager_comment}"
                : $this->demande->comment,
            'type_conge' => $this->demande->type_conge->value,
        ];
    }

    private function subject(): string
    {
        return match (true) {
            $this->cancellationKept => "Votre demande d'annulation a été refusée",
            $this->demande->etat === LeaveStatus::Accepted => 'Votre demande de congé a été acceptée',
            $this->demande->etat === LeaveStatus::Cancelled => 'Votre congé a été annulé',
            default => 'Votre demande de congé a été refusée',
        };
    }

    private function message(): string
    {
        return match (true) {
            $this->cancellationKept => "Votre demande d'annulation a été refusée : votre congé est maintenu.",
            $this->demande->etat === LeaveStatus::Accepted => 'Votre demande de congé a été acceptée.',
            $this->demande->etat === LeaveStatus::Cancelled => 'Votre congé a été annulé et ses jours vous sont rendus.',
            $this->byManager => 'Votre demande de congé a été refusée par votre responsable.',
            default => 'Votre demande de congé a été refusée.',
        };
    }

    private function formatDate(mixed $date): string
    {
        return Carbon::parse($date)->format('d/m/Y');
    }
}
