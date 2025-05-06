<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Demande;

class DemandeStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    protected $demande;

    public function __construct(Demande $demande)
    {
        $this->demande = $demande;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        $status = $this->demande->etat;
        $message = $status === 'acceptée' 
            ? 'Votre demande de congé a été acceptée.' 
            : 'Votre demande de congé a été refusée.';

        return (new MailMessage)
            ->subject('Mise à jour de votre demande de congé')
            ->line($message)
            ->line('Détails de la demande:')
            ->line("Date de début: {$this->demande->date_debut}")
            ->line("Date de fin: {$this->demande->date_fin}")
            ->line("Nombre de jours: {$this->demande->nbr_jours}")
            ->line("Commentaire: {$this->demande->comment}")
            ->action('Voir la demande', url('/demandes'));
    }

    public function toArray($notifiable)
    {
        return [
            'demande_id' => $this->demande->id,
            'status' => $this->demande->etat,
            'message' => $this->demande->etat === 'acceptée' 
                ? 'Votre demande de congé a été acceptée.' 
                : 'Votre demande de congé a été refusée.',
            'date_debut' => $this->demande->date_debut,
            'date_fin' => $this->demande->date_fin,
            'nbr_jours' => $this->demande->nbr_jours,
            'comment' => $this->demande->comment,
        ];
    }
} 