<?php

namespace App\Notifications;

use App\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Asks an employee to read and accept the data protection notice. By e-mail (they may not have signed in yet). */
class PrivacyNoticeReminder extends Notification
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = CompanySetting::current()->name ?? config('app.name');

        return (new MailMessage)
            ->subject('Protection de vos données : une notice à lire')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$company} gère vos congés et votre dossier administratif dans l'application. La loi 09-08 prévoit que vous soyez informé de l'usage de vos données personnelles.")
            ->line('Merci de vous connecter pour lire la notice et confirmer en avoir pris connaissance : elle s\'affiche dès la connexion.')
            ->action('Lire la notice', route('privacy.notice'))
            ->line('Pas encore de mot de passe ? Utilisez « Mot de passe oublié » sur la page de connexion.');
    }
}
