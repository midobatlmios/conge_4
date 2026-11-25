<?php

namespace App\Notifications;

use App\Models\Demande;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Tells a manager that someone in their team is on sick leave. For
 * information only: sick leave is not approved by the manager, HR validates
 * it once the medical certificate is in. No medical detail is shared.
 */
class SickLeaveDeclared extends Notification
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
        return (new MailMessage)
            ->subject("Arrêt maladie : {$this->demande->user->name}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->message())
            ->line('Pour information : aucune validation n\'est attendue de votre part.')
            ->action('Voir le calendrier', route('calendar'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'demande_id' => $this->demande->id,
            'message' => $this->message(),
            'date_debut' => $this->demande->date_debut,
            'date_fin' => $this->demande->date_fin,
            'type_conge' => $this->demande->type_conge->value,
        ];
    }

    private function message(): string
    {
        return "{$this->demande->user->name} est en arrêt maladie du "
            .Carbon::parse($this->demande->date_debut)->format('d/m/Y').' au '
            .Carbon::parse($this->demande->date_fin)->format('d/m/Y').'.';
    }
}
