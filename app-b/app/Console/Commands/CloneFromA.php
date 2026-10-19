<?php

namespace App\Console\Commands;

use App\Services\FinanceSnapshotPublisher;
use App\Services\ScheduledDatabaseClone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('finance:clone-from-a')]
#[Description('B: clone MSSQL A ke B, lalu terbitkan snapshot setelah restore sukses')]
class CloneFromA extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScheduledDatabaseClone $clone, FinanceSnapshotPublisher $publisher): int
    {
        try {
            Cache::store(config('finance_sync.lock_store'))->lock('finance-clone-pipeline', 7200)->block(1, function () use ($clone, $publisher): void {
                $clone->run();
                $manifest = $publisher->publish();
                $this->info('Clone A ke B dan snapshot berhasil: '.$manifest['snapshot_id']);
            });

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Pipeline clone gagal. Periksa log B sebelum menarik data ke C.');

            return self::FAILURE;
        }
    }
}
