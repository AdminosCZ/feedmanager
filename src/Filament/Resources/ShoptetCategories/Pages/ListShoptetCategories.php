<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategories\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategoryResource;
use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Models\ImportLog;
use Adminos\Modules\Feedmanager\Services\ShoptetCategorySyncService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

final class ListShoptetCategories extends ListRecords
{
    protected static string $resource = ShoptetCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_now')
                ->label(__('feedmanager::feedmanager.actions.sync_categories'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn (): bool => self::categoryFeedConfigsQuery()->exists())
                ->schema(fn () => self::syncFormSchema())
                ->modalHeading(__('feedmanager::feedmanager.actions.sync_categories_heading'))
                ->modalDescription(__('feedmanager::feedmanager.actions.sync_categories_description'))
                ->action(function (array $data): void {
                    /** @var FeedConfig|null $config */
                    $config = FeedConfig::query()->find($data['feed_config_id'] ?? null);

                    if ($config === null || ! $config->isCategoryFeed()) {
                        Notification::make()
                            ->title(__('feedmanager::feedmanager.notifications.sync_categories_no_config'))
                            ->danger()
                            ->send();

                        return;
                    }

                    /** @var ShoptetCategorySyncService $service */
                    $service = app(ShoptetCategorySyncService::class);
                    $log = $service->run($config, ImportLog::TRIGGER_MANUAL);

                    if ($log->status === ImportLog::STATUS_SUCCESS) {
                        Notification::make()
                            ->title(__('feedmanager::feedmanager.notifications.sync_categories_done'))
                            ->body($config->last_message)
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title(__('feedmanager::feedmanager.notifications.sync_categories_failed'))
                            ->body($log->message)
                            ->danger()
                            ->send();
                    }
                }),
            CreateAction::make(),
        ];
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private static function syncFormSchema(): array
    {
        $configs = self::categoryFeedConfigsQuery()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return [
            Select::make('feed_config_id')
                ->label(__('feedmanager::feedmanager.fields.feed_config'))
                ->options($configs)
                ->default(array_key_first($configs))
                ->required()
                ->native(false),
        ];
    }

    private static function categoryFeedConfigsQuery()
    {
        return FeedConfig::query()
            ->where('format', FeedConfig::FORMAT_SHOPTET_CATEGORIES)
            ->where('is_active', true);
    }
}
