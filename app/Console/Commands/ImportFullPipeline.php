<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportFullPipeline extends Command
{
    protected $signature = 'import:full-pipeline
        {--dry-run : Preview changes without writing to database}';

    protected $description = 'Run the complete WooCommerce import pipeline (additive-only)';

    /**
     * Pipeline steps in execution order.
     *
     * @var list<array{command: string, label: string, passthrough: list<string>}>
     */
    private const STEPS = [
        ['command' => 'import:legacy-data', 'label' => 'Import legacy data', 'passthrough' => ['--dry-run']],
        ['command' => 'products:sync-categories', 'label' => 'Sync product categories', 'passthrough' => []],
        ['command' => 'products:sync-tags', 'label' => 'Sync product tags', 'passthrough' => []],
        ['command' => 'variants:assign-types', 'label' => 'Assign variant types', 'passthrough' => ['--dry-run']],
        ['command' => 'media:associate-legacy', 'label' => 'Associate legacy media', 'passthrough' => ['--dry-run']],
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('=== WooCommerce Import Pipeline ===');
        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }
        $this->info('All steps are additive-only. Existing data will not be modified.');
        $this->newLine();

        foreach (self::STEPS as $index => $step) {
            $stepNumber = $index + 1;
            $total = count(self::STEPS);
            $this->info("[{$stepNumber}/{$total}] {$step['label']}...");

            $arguments = ['--no-interaction' => true];
            if ($dryRun && in_array('--dry-run', $step['passthrough'], true)) {
                $arguments['--dry-run'] = true;
            }

            $exitCode = $this->call($step['command'], $arguments);

            if ($exitCode !== 0) {
                $this->error("Step '{$step['label']}' failed with exit code {$exitCode}. Aborting pipeline.");

                return $exitCode;
            }

            $this->newLine();
        }

        $this->info('=== Pipeline Complete ===');

        return 0;
    }
}
