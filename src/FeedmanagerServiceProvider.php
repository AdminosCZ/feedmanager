<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager;

use Adminos\Modules\Feedmanager\Console\Commands\ImportFeedsCommand;
use Adminos\Modules\Feedmanager\Services\CategoryMappingService;
use Adminos\Modules\Feedmanager\Services\FeedDownloader;
use Adminos\Modules\Feedmanager\Services\FeedExplorerService;
use Adminos\Modules\Feedmanager\Services\FeedImporter;
use Adminos\Modules\Feedmanager\Services\Parsing\FeedParserFactory;
use Adminos\Modules\Feedmanager\Services\B2bInclusion\B2bInclusionResolver;
use Adminos\Modules\Feedmanager\Services\Parsing\ShoptetCategoriesParser;
use Adminos\Modules\Feedmanager\Services\RuleEngine\RuleEngine;
use Adminos\Modules\Feedmanager\Services\ShoptetCategorySyncService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * @api
 */
final class FeedmanagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeedParserFactory::class);
        $this->app->singleton(RuleEngine::class);
        $this->app->singleton(CategoryMappingService::class);
        $this->app->bind(FeedDownloader::class, fn ($app) => new FeedDownloader($app->make(HttpFactory::class)));
        $this->app->bind(
            FeedExplorerService::class,
            fn ($app) => new FeedExplorerService($app->make(HttpFactory::class), $app->make(FeedParserFactory::class)),
        );
        $this->app->singleton(ShoptetCategoriesParser::class);
        $this->app->singleton(ShoptetCategorySyncService::class);
        // B2bInclusionResolver má cache excluded category set per instance —
        // singleton sdílí cache napříč voláními v jednom requestu, ale přežije
        // i mezi Livewire requesty (cache se musí flushnout po změně flagů).
        $this->app->singleton(B2bInclusionResolver::class);
        $this->app->singleton(FeedImporter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'feedmanager');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'feedmanager');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportFeedsCommand::class,
            ]);
        }
    }
}
