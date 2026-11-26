<?php

namespace App\Notifications;

use App\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Welcome e-mail with a link to choose a password (valid 7 days). */
class EmployeeInvitation extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = CompanySetting::current()->name ?? config('app.name');
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset(), 'invitation' => 1]);

        return (new MailMessage)
            ->subject("{$company} : votre accès à l'espace congés et RH")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le service RH de {$company} vous a créé un compte pour gérer vos congés, consulter votre solde et demander vos attestations.")
            ->line("Votre identifiant est votre adresse e-mail : {$notifiable->email}")
            ->action('Choisir mon mot de passe', $url)
            ->line('Ce lien est valable 7 jours. Passé ce délai, demandez un nouveau lien au service RH ou utilisez « Mot de passe oublié ».');
    }
}
