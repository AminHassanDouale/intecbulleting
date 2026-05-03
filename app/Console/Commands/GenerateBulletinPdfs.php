<?php

namespace App\Console\Commands;

use App\Actions\Bulletin\GenerateBulletinPdfAction;
use App\Enums\BulletinStatusEnum;
use App\Models\Bulletin;
use Illuminate\Console\Command;

class GenerateBulletinPdfs extends Command
{
    protected $signature = 'bulletins:generate-pdfs
                            {--period= : Limit to T1, T2 or T3}
                            {--classroom= : Limit to a classroom code (e.g. CP-A)}
                            {--force : Regenerate even if a PDF already exists}
                            {--skip-empty : Skip bulletins with no grades}';

    protected $description = 'Generate and store PDF files for all published bulletins';

    public function handle(GenerateBulletinPdfAction $action): int
    {
        $query = Bulletin::with(['student.classroom.niveau', 'grades.competence.subject', 'academicYear'])
            ->where('status', BulletinStatusEnum::PUBLISHED);

        if ($period = $this->option('period')) {
            $query->where('period', strtoupper($period));
        }

        if ($classroom = $this->option('classroom')) {
            $query->whereHas('classroom', fn($q) => $q->where('code', $classroom));
        }

        if (! $this->option('force')) {
            $query->whereDoesntHave('media', fn($q) => $q->where('collection_name', 'bulletin_pdf'));
        }

        $bulletins = $query->get();

        if ($bulletins->isEmpty()) {
            $this->info('No bulletins to process.');
            return 0;
        }

        $skipEmpty  = $this->option('skip-empty');
        $total      = $bulletins->count();
        $done       = 0;
        $skipped    = 0;
        $errors     = 0;

        $this->info("Generating PDFs for {$total} bulletin(s)…");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($bulletins as $bulletin) {
            $bar->advance();

            if ($skipEmpty && $bulletin->grades->isEmpty()) {
                $skipped++;
                continue;
            }

            try {
                $action->execute($bulletin);
                $done++;
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->error("  Bulletin #{$bulletin->id} ({$bulletin->student->full_name} — {$bulletin->period}): {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Generated', 'Skipped (no grades)', 'Errors'],
            [[$done, $skipped, $errors]]
        );

        return $errors > 0 ? 1 : 0;
    }
}
