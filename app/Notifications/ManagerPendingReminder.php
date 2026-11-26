<?php

namespace App\Notifications;

use App\Models\Demande;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Reminds a manager of team requests still waiting for their validation. In the app and by e-mail. */
class ManagerPendingReminder extends Notification
{
    use Queueable;

    /** @param Collection<int, Demande> $requests */
    public function __construct(private readonly Collection $requests) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->message())
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Ces demandes de votre équipe attendent votre validation depuis plusieurs jours :');

        foreach ($this->requests as $demande) {
            $mail->line("- {$demande->user->name} : {$demande->type_conge->label()} du "
                .Carbon::parse($demande->date_debut)->format('d/m/Y').' au '.Carbon::parse($demande->date_fin)->format('d/m/Y'));
        }

        return $mail
            ->line('Les RH ne décident qu\'après votre avis : sans réponse, vos collaborateurs restent dans l\'attente.')
            ->action('Voir les demandes de mon équipe', route('team'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'comment' => $this->requests->map(fn (Demande $d) => $d->user->name)->unique()->implode(', '),
        ];
    }

    private function message(): string
    {
        $count = $this->requests->count();

        return $count > 1 ? "{$count} demandes attendent votre validation" : 'Une demande attend votre validation';
    }
}
