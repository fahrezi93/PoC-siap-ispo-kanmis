<?php

namespace App\Console\Commands;

use App\Services\FinanceMirrorSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('finance:sync-from-b')]
#[Description('C: unduh snapshot B dan ganti tabel mirror secara atomik')]
class SyncFinanceMirror extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FinanceMirrorSync $sync): int
    {
        try {
            $this->info($sync->sync());

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Sinkronisasi gagal. Data C sebelumnya dipertahankan. Periksa log server.');

            return self::FAILURE;
        }
    }
}
