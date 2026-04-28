<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Products\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\ProductResource;
use Adminos\Modules\Feedmanager\Filament\Resources\Products\Schemas\ProductInfolist;
use Adminos\Modules\Feedmanager\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

final class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    public function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggle_b2b')
                ->label(fn (Product $record): string => $record->is_b2b_allowed
                    ? __('feedmanager::feedmanager.actions.remove_from_b2b')
                    : __('feedmanager::feedmanager.actions.add_to_b2b'))
                ->icon(fn (Product $record): string => $record->is_b2b_allowed
                    ? 'heroicon-o-x-circle'
                    : 'heroicon-o-check-circle')
                ->color(fn (Product $record): string => $record->is_b2b_allowed ? 'danger' : 'success')
                ->action(function (Product $record): void {
                    $record->update(['is_b2b_allowed' => ! $record->is_b2b_allowed]);
                    Notification::make()
                        ->title($record->is_b2b_allowed
                            ? __('feedmanager::feedmanager.notifications.b2b_added')
                            : __('feedmanager::feedmanager.notifications.b2b_removed'))
                        ->success()
                        ->send();
                }),
            Action::make('approve')
                ->label(__('feedmanager::feedmanager.actions.approve'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (Product $record): bool => $record->status !== Product::STATUS_APPROVED)
                ->action(function (Product $record): void {
                    $record->update(['status' => Product::STATUS_APPROVED]);
                    Notification::make()
                        ->title(__('feedmanager::feedmanager.notifications.approved'))
                        ->success()
                        ->send();
                }),
            Action::make('reject')
                ->label(__('feedmanager::feedmanager.actions.reject'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (Product $record): bool => $record->status !== Product::STATUS_REJECTED)
                ->requiresConfirmation()
                ->action(function (Product $record): void {
                    $record->update(['status' => Product::STATUS_REJECTED]);
                    Notification::make()
                        ->title(__('feedmanager::feedmanager.notifications.rejected'))
                        ->warning()
                        ->send();
                }),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
