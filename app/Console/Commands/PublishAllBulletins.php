<?php

namespace App\Console\Commands;

use App\Enums\BulletinStatusEnum;
use App\Models\Bulletin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishAllBulletins extends Command
{
    protected $signature   = 'bulletins:publish-all {--period= : Limit to a specific period (T1, T2, T3)}';
    protected $description = 'Force-publish every bulletin regardless of current workflow status';

    public function handle(): int
    {
        $period = $this->option('period');

        $query = Bulletin::where('status', '!=', BulletinStatusEnum::PUBLISHED->value);

        if ($period) {
            $query->where('period', strtoupper($period));
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('All bulletins are already published.');
            return self::SUCCESS;
        }

        $label = $period ? "period {$period}" : 'all periods';
        if (! $this->confirm("Publish {$total} bulletin(s) ({$label})?", true)) {
            $this->line('Aborted.');
            return self::SUCCESS;
        }

        $now = now();

        DB::transaction(function () use ($query, $now) {
            $query->chunkById(200, function ($bulletins) use ($now) {
                $ids = $bulletins->pluck('id');
                Bulletin::whereIn('id', $ids)->update([
                    'status'       => BulletinStatusEnum::PUBLISHED->value,
                    'published_at' => $now,
                    'updated_at'   => $now,
                ]);
            });
        });

        $this->info("✓ {$total} bulletin(s) published successfully.");

        return self::SUCCESS;
    }
}
