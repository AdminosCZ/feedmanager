<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\Products\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\ProductResource;
use Adminos\Modules\Feedmanager\Filament\Resources\Products\Schemas\ProductInfolist;
use Adminos\Modules\Feedmanager\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

final class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        // Override Filament's auto-generated "View {recordTitle}" — the heading
        // is already the product name, prefix is redundant noise.
        /** @var Product $record */
        $record = $this->getRecord();

        return $record->effectiveName();
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->getTitle();
    }

    public function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    /**
     * Suppress relation managers on the View page — Images and Parameters
     * already render inline in the infolist (Gallery + Parameters sections),
     * so the auto-tabs at the bottom would just duplicate the same data.
     * The Edit page still has the relation managers for actual CRUD.
     */
    protected function getAllRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Primary destination: edit. Visually anchors the toolbar.
            EditAction::make(),

            // Approval flow — only one of approve/reject is visible at a time.
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
                ->color('warning')
                ->visible(fn (Product $record): bool => $record->status !== Product::STATUS_REJECTED)
                ->requiresConfirmation()
                ->action(function (Product $record): void {
                    $record->update(['status' => Product::STATUS_REJECTED]);
                    Notification::make()
                        ->title(__('feedmanager::feedmanager.notifications.rejected'))
                        ->warning()
                        ->send();
                }),

            // Less-common toggles + delete grouped behind a single trigger.
            ActionGroup::make([
                Action::make('toggle_b2b')
                    ->label(fn (Product $record): string => $record->is_b2b_allowed
                        ? __('feedmanager::feedmanager.actions.remove_from_b2b')
                        : __('feedmanager::feedmanager.actions.add_to_b2b'))
                    ->icon(fn (Product $record): string => $record->is_b2b_allowed
                        ? 'heroicon-o-x-circle'
                        : 'heroicon-o-check-circle')
                    ->color(fn (Product $record): string => $record->is_b2b_allowed ? 'warning' : 'success')
                    ->action(function (Product $record): void {
                        $record->update(['is_b2b_allowed' => ! $record->is_b2b_allowed]);
                        Notification::make()
                            ->title($record->is_b2b_allowed
                                ? __('feedmanager::feedmanager.notifications.b2b_added')
                                : __('feedmanager::feedmanager.notifications.b2b_removed'))
                            ->success()
                            ->send();
                    }),
                Action::make('toggle_excluded')
                    ->label(fn (Product $record): string => $record->is_excluded
                        ? __('feedmanager::feedmanager.actions.include_in_export')
                        : __('feedmanager::feedmanager.actions.exclude_from_export'))
                    ->icon(fn (Product $record): string => $record->is_excluded
                        ? 'heroicon-o-eye'
                        : 'heroicon-o-eye-slash')
                    ->color(fn (Product $record): string => $record->is_excluded ? 'success' : 'warning')
                    ->action(function (Product $record): void {
                        $record->update(['is_excluded' => ! $record->is_excluded]);
                        Notification::make()
                            ->title($record->is_excluded
                                ? __('feedmanager::feedmanager.notifications.excluded')
                                : __('feedmanager::feedmanager.notifications.included'))
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
                ->label(__('feedmanager::feedmanager.actions.more'))
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray'),
        ];
    }
}
