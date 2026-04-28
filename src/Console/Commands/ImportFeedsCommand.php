<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Console\Commands;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Services\FeedImporter;
use Illuminate\Console\Command;

/**
 * @api
 */
final class ImportFeedsCommand extends Command
{
    protected $signature = 'feedmanager:import
                            {feed_config? : Run a single FeedConfig by id; omit to run all auto-update + active configs}
                            {--manual : Mark the run as manually triggered (default for explicit ids)}';

    protected $description = 'Imports feedmanager FeedConfig source URLs into the catalog.';

    public function handle(FeedImporter $importer): int
    {
        $configIdArg = $this->argument('feed_config');
        $configs = $this->resolveConfigs($configIdArg !== null ? (int) $configIdArg : null);

        if ($configs->isEmpty()) {
            $this->info('No feed configs to import.');
            return self::SUCCESS;
        }

        $triggeredBy = $configIdArg !== null || $this->option('manual')
            ? ImportLog::TRIGGER_MANUAL
            : ImportLog::TRIGGER_CRON;

        $hasFailure = false;

        foreach ($configs as $config) {
            $this->line("[{$config->id}] {$config->name} ({$config->format}) — running…");

            $log = $importer->run($config, $triggeredBy);

            if ($log->status === ImportLog::STATUS_SUCCESS) {
                $this->info(sprintf(
                    '[%d] OK — found %d, new %d, updated %d, failed %d.',
                    $config->id,
                    $log->products_found,
                    $log->products_new,
                    $log->products_updated,
                    $log->products_failed,
                ));
            } else {
                $hasFailure = true;
                $this->error(sprintf(
                    '[%d] FAILED — %s',
                    $config->id,
                    $log->message ?? 'unknown error',
                ));
            }
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, FeedConfig>
     */
    private function resolveConfigs(?int $explicitId): \Illuminate\Database\Eloquent\Collection
    {
        if ($explicitId !== null) {
            $config = FeedConfig::query()->find($explicitId);

            if ($config === null) {
                $this->error("FeedConfig #{$explicitId} not found.");
                return new \Illuminate\Database\Eloquent\Collection();
            }

            return new \Illuminate\Database\Eloquent\Collection([$config]);
        }

        return FeedConfig::query()
            ->where('is_active', true)
            ->where('auto_update', true)
            ->orderBy('id')
            ->get();
    }
}
