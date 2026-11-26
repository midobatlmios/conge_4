<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * HR's morning summary: what needs action (requests waiting, CNSS dossiers,
 * contracts ending, documents expiring…). In the app and by e-mail.
 */
class HrDailySummary extends Notification
{
    use Queueable;

    /** Lines per section in the e-mail; the rest is "… et N autres". */
    private const MAX_LINES = 8;

    /** @param list<array{title: string, items: list<string>}> $sections  only non-empty ones */
    public function __construct(private readonly array $sections) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('À faire aujourd\'hui : '.$this->total().' point(s)')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Voici ce qui attend le service RH ce matin.');

        foreach ($this->sections as $section) {
            $mail->line("**{$section['title']} (".count($section['items']).')**');
            foreach (array_slice($section['items'], 0, self::MAX_LINES) as $item) {
                $mail->line("- {$item}");
            }
            if (count($section['items']) > self::MAX_LINES) {
                $mail->line('- … et '.(count($section['items']) - self::MAX_LINES).' autre(s)');
            }
        }

        return $mail->action('Ouvrir la vue d\'ensemble', route('admin.overview'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'À faire aujourd\'hui : '.$this->total().' point(s) pour le service RH.',
            'comment' => $this->headline(),
        ];
    }

    private function total(): int
    {
        return array_sum(array_map(fn (array $s) => count($s['items']), $this->sections));
    }

    /** "Demandes en attente de décision : 3 · Dossiers CNSS à déposer : 1" */
    private function headline(): string
    {
        return implode(' · ', array_map(fn (array $s) => $s['title'].' : '.count($s['items']), $this->sections));
    }
}
