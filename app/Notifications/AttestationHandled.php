<?php

namespace App\Notifications;

use App\Enums\AttestationStatus;
use App\Models\Attestation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the employee their attestation is ready, or was refused. In the app and by email. */
class AttestationHandled extends Notification
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
            ->subject($this->issued() ? 'Votre attestation est prête' : "Votre demande d'attestation a été refusée")
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->message());

        if (! $this->issued()) {
            $mail->line("**Motif :** {$this->attestation->refusal_reason}");
        }

        return $mail->action('Mes attestations', route('my-attestations'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'attestation_id' => $this->attestation->id,
            'message' => $this->message(),
            'comment' => $this->issued() ? null : $this->attestation->refusal_reason,
        ];
    }

    private function issued(): bool
    {
        return $this->attestation->status === AttestationStatus::Issued;
    }

    private function message(): string
    {
        $label = $this->attestation->type->label();

        return $this->issued()
            ? "Votre {$this->lower($label)} (n° {$this->attestation->number}) est prête : téléchargez-la dans « Mes attestations »."
            : "Votre demande d'{$this->lower($label)} a été refusée.";
    }

    private function lower(string $label): string
    {
        return lcfirst($label);
    }
}
