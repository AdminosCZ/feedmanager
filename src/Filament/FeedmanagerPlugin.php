<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament;

use Adminos\Modules\Feedmanager\Filament\Pages\FeedExplorerPage;
use Adminos\Modules\Feedmanager\Filament\Resources\ExportConfigResource;
use Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigResource;
use Adminos\Modules\Feedmanager\Filament\Resources\FeedRuleResource;
use Adminos\Modules\Feedmanager\Filament\Resources\ProductResource;
use Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategoryResource;
use Adminos\Modules\Feedmanager\Filament\Resources\SupplierCategoryResource;
use Adminos\Modules\Feedmanager\Filament\Resources\SupplierResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * @api
 */
final class FeedmanagerPlugin implements Plugin
{
    public function getId(): string
    {
        return 'feedmanager';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            ProductResource::class,
            SupplierResource::class,
            FeedConfigResource::class,
            FeedRuleResource::class,
            ShoptetCategoryResource::class,
            SupplierCategoryResource::class,
            ExportConfigResource::class,
        ]);

        $panel->pages([
            FeedExplorerPage::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
    }

    public static function make(): self
    {
        return new self();
    }
}
