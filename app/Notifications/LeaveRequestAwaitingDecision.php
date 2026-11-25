<?php

namespace App\Notifications;

use App\Models\Demande;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Tells a manager that a request from their team waits for their approval, or
 * HR that a request waits for the final decision. In the app and by email,
 * sent immediately (see DemandeStatusChanged).
 */
class LeaveRequestAwaitingDecision extends Notification
{
    use Queueable;

    /** @param bool $forManager The manager's step; otherwise HR's. */
    public function __construct(protected Demande $demande, protected bool $forManager) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->forManager ? 'Une demande de congé attend votre validation' : 'Une demande de congé attend la décision RH')
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->message())
            ->line('**Type :** '.$this->demande->type_conge->label())
            ->line('**Du :** '.$this->formatDate($this->demande->date_debut).' **au :** '.$this->formatDate($this->demande->date_fin))
            ->line('**Nombre de jours :** '.str_replace('.', ',', (string) (float) $this->demande->nbr_jours))
            ->action($this->forManager ? 'Voir les demandes de mon équipe' : 'Gérer les demandes', route($this->forManager ? 'team' : 'demandes.index'));
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
        $name = $this->demande->user->name;

        return $this->forManager
            ? "{$name} demande un congé : à valider en tant que responsable."
            : ($this->demande->etat->value === 'validée manager'
                ? "La demande de congé de {$name} a été validée par son responsable : décision RH attendue."
                : "Nouvelle demande de congé de {$name} : décision RH attendue.");
    }

    private function formatDate(mixed $date): string
    {
        return Carbon::parse($date)->format('d/m/Y');
    }
}
