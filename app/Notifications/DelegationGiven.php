<?php

namespace App\Notifications;

use App\Models\ApprovalDelegation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the delegate they will approve a manager's team requests for a period. In the app and by e-mail. */
class DelegationGiven extends Notification
{
    use Queueable;

    public function __construct(private readonly ApprovalDelegation $delegation) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Délégation : vous validez les demandes de l'équipe de {$this->delegation->manager->name}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->message())
            ->line('Vous recevrez les demandes à valider et leurs rappels ; vous les trouverez dans « Mon équipe ».')
            ->action('Ouvrir « Mon équipe »', route('team'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message()];
    }

    private function message(): string
    {
        return "{$this->delegation->manager->name} vous confie la validation des demandes de son équipe du "
            .$this->delegation->starts_on->format('d/m/Y').' au '.$this->delegation->ends_on->format('d/m/Y').'.';
    }
}
