<?php

namespace App\Console\Commands;

use App\Services\Reminders;
use Illuminate\Console\Command;

/**
 * Sends the morning reminders (managers, HR summary, data protection notice).
 * Scheduled each working day in routes/console.php.
 */
class SendReminders extends Command
{
    protected $signature = 'reminders:send {--dry-run : List what would be sent, without sending}';

    protected $description = 'Send the daily reminders (requests waiting, HR summary, data protection notice)';

    public function handle(Reminders $reminders): int
    {
        $done = $reminders->run(send: ! $this->option('dry-run'));

        if ($done['skipped']) {
            $this->info($done['skipped']);

            return self::SUCCESS;
        }

        $this->table(['Rappel', $this->option('dry-run') ? 'À envoyer' : 'Envoyés'], [
            ['Responsables (demandes à valider)', $done['managers']],
            ['Résumé RH', $done['hr']],
            ['Notice de protection des données', $done['notice']],
        ]);

        return self::SUCCESS;
    }
}
