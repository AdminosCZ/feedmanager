<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigs\Pages;

use Adminos\Modules\Feedmanager\Filament\Resources\FeedConfigResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateFeedConfig extends CreateRecord
{
    protected static string $resource = FeedConfigResource::class;

    /**
     * When admin clicks „Přidat feed" from the supplier/own-eshop detail,
     * the URL carries `?supplier_id=X` — pre-fill the form so they don't
     * have to pick the supplier manually.
     */
    protected function fillForm(): void
    {
        parent::fillForm();

        $supplierId = request()->integer('supplier_id');
        if ($supplierId > 0) {
            $this->form->fill([
                ...$this->form->getRawState(),
                'supplier_id' => $supplierId,
            ]);
        }
    }

    /**
     * Save / Cancel se přesouvají do hlavičky.
     *
     * @return array<int, mixed>
     */
    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label(__('feedmanager::feedmanager.actions.save'))
                ->color('success')
                ->icon('heroicon-m-check'),
            $this->getCancelFormAction()
                ->label(__('feedmanager::feedmanager.actions.back'))
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('gray'),
        ];
    }
}
