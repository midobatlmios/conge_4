<?php

namespace App\Console\Commands;

use App\Services\DataRetention;
use Illuminate\Console\Command;

/**
 * Lists, then with --apply deletes, the personal data past its retention
 * period (config conge.retention). Meant for a scheduled job later (6.9).
 */
class PrivacyRetention extends Command
{
    protected $signature = 'privacy:retention {--apply : Delete what is due (otherwise only list it)}';

    protected $description = 'Apply the personal data retention periods (loi 09-08)';

    public function handle(DataRetention $retention): int
    {
        $due = $retention->due();

        $this->table(['À traiter', 'Nombre'], [
            ['Certificats médicaux', $due['certificates']->count()],
            ['Dossiers d\'anciens employés', $due['personal']->count()],
            ['Anonymisations', $due['anonymize']->count()],
            ['Candidatures expirées', $due['candidates']->count()],
        ]);

        if (! $this->option('apply')) {
            $this->line('Rien n\'a été supprimé. Relancez avec --apply pour appliquer.');

            return self::SUCCESS;
        }

        $retention->apply(null);
        $this->info('Conservation appliquée.');

        return self::SUCCESS;
    }
}
