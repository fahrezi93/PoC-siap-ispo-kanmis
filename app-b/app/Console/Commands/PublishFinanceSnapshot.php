<?php

namespace App\Console\Commands;

use App\Services\FinanceSnapshotPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('finance:publish-snapshot')]
#[Description('B: terbitkan snapshot MSSQL konsisten untuk aplikasi C')]
class PublishFinanceSnapshot extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FinanceSnapshotPublisher $publisher): int
    {
        try {
            $manifest = $publisher->publish();
            $this->info('Snapshot diterbitkan: '.$manifest['snapshot_id']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Publikasi gagal. Snapshot sebelumnya tetap digunakan. Periksa log server.');

            return self::FAILURE;
        }
    }
}
