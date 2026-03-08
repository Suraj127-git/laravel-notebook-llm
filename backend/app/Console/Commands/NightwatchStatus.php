<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NightwatchStatus extends Command
{
    protected $signature = 'nightwatch:status';

    protected $description = 'Show Nightwatch logging and AI pipeline status';

    public function handle(): int
    {
        $this->info('=== Nightwatch / AI Pipeline Status ===');
        $this->newLine();

        // Log channel
        $this->line('<fg=yellow>Log channels:</> nightwatch, ai_events');
        $aiLog = storage_path('logs/ai_events.log');
        $this->line('AI events log: '.($this->fileStatus($aiLog)));
        $this->newLine();

        // Queue depth
        try {
            $pending = DB::table('jobs')->count();
            $failed  = DB::table('failed_jobs')->count();
            $this->line("<fg=yellow>Queue:</> {$pending} pending, {$failed} failed");
        } catch (\Throwable $e) {
            $this->warn('Queue table not readable: '.$e->getMessage());
        }

        // Document stats
        try {
            $docs = DB::table('documents')
                ->selectRaw('status, count(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status');

            $this->newLine();
            $this->line('<fg=yellow>Documents:</>');
            foreach ($docs as $status => $cnt) {
                $this->line("  {$status}: {$cnt}");
            }
        } catch (\Throwable $e) {
            $this->warn('Documents table not readable: '.$e->getMessage());
        }

        // AI usage
        try {
            $usage = DB::table('ai_usage_logs')
                ->selectRaw('provider, sum(tokens_in) as tokens_in, sum(tokens_out) as tokens_out')
                ->groupBy('provider')
                ->get();

            if ($usage->isNotEmpty()) {
                $this->newLine();
                $this->line('<fg=yellow>AI Usage (all time):</>');
                foreach ($usage as $row) {
                    $this->line("  {$row->provider}: {$row->tokens_in} in / {$row->tokens_out} out");
                }
            }
        } catch (\Throwable $e) {
            // Table may not exist yet
        }

        $this->newLine();
        $this->info('Status check complete.');

        return self::SUCCESS;
    }

    private function fileStatus(string $path): string
    {
        if (! file_exists($path)) {
            return '<fg=red>not found</>';
        }

        $size = round(filesize($path) / 1024, 1);

        return "<fg=green>exists</> ({$size} KB)";
    }
}
