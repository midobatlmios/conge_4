<?php

namespace App\Notifications;

use App\Models\Attestation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells HR (Admin, RH) that an employee asks for an attestation. In the app and by email. */
class AttestationRequested extends Notification
{
    use Queueable;

    public function __construct(protected Attestation $attestation) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Demande d'attestation de {$this->attestation->user->name}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->message());

        if ($this->attestation->purpose) {
            $mail->line("**Motif :** {$this->attestation->purpose}");
        }

        return $mail->action('Voir les attestations', route('admin.attestations.index'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'attestation_id' => $this->attestation->id,
            'message' => $this->message(),
            'comment' => $this->attestation->purpose,
        ];
    }

    private function message(): string
    {
        return "{$this->attestation->user->name} demande une ".lcfirst($this->attestation->type->label()).'.';
    }
}
