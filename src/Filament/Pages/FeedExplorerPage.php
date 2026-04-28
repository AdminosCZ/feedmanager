<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Pages;

use Adminos\Modules\Feedmanager\Models\FeedConfig;
use Adminos\Modules\Feedmanager\Services\FeedExplorerService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * @api
 */
final class FeedExplorerPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = 80;

    protected string $view = 'feedmanager::pages.feed-explorer';

    public ?array $data = [
        'url' => null,
        'format' => FeedConfig::FORMAT_HEUREKA,
        'http_username' => null,
        'http_password' => null,
    ];

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public ?string $error = null;

    public static function getNavigationLabel(): string
    {
        return __('feedmanager::feedmanager.explorer.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('feedmanager::feedmanager.navigation.group');
    }

    public function getTitle(): string
    {
        return __('feedmanager::feedmanager.explorer.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('feedmanager::feedmanager.explorer.sections.source'))
                    ->columns(2)
                    ->components([
                        TextInput::make('url')
                            ->label(__('feedmanager::feedmanager.fields.source_url'))
                            ->url()
                            ->required()
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        Select::make('format')
                            ->label(__('feedmanager::feedmanager.fields.format'))
                            ->options([
                                FeedConfig::FORMAT_HEUREKA => __('feedmanager::feedmanager.formats.heureka'),
                                FeedConfig::FORMAT_GOOGLE => __('feedmanager::feedmanager.formats.google'),
                                FeedConfig::FORMAT_SHOPTET => __('feedmanager::feedmanager.formats.shoptet'),
                                FeedConfig::FORMAT_ZBOZI => __('feedmanager::feedmanager.formats.zbozi'),
                                FeedConfig::FORMAT_CUSTOM => __('feedmanager::feedmanager.formats.custom'),
                            ])
                            ->default(FeedConfig::FORMAT_HEUREKA)
                            ->native(false)
                            ->required(),
                    ]),

                Section::make(__('feedmanager::feedmanager.explorer.sections.auth'))
                    ->description(__('feedmanager::feedmanager.explorer.auth_help'))
                    ->columns(2)
                    ->collapsed()
                    ->components([
                        TextInput::make('http_username')
                            ->label(__('feedmanager::feedmanager.fields.http_username'))
                            ->maxLength(255),
                        TextInput::make('http_password')
                            ->label(__('feedmanager::feedmanager.fields.http_password'))
                            ->password()
                            ->revealable()
                            ->maxLength(1024),
                    ]),
            ]);
    }

    public function analyze(FeedExplorerService $service): void
    {
        $state = $this->form->getState();

        $this->result = null;
        $this->error = null;

        try {
            $this->result = $service->analyzeUrl(
                url: (string) $state['url'],
                format: (string) $state['format'],
                username: $state['http_username'] ?: null,
                password: $state['http_password'] ?: null,
            );
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            Notification::make()
                ->title(__('feedmanager::feedmanager.notifications.explorer_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('feedmanager::feedmanager.notifications.explorer_done', [
                'count' => $this->result['total_products'],
            ]))
            ->success()
            ->send();
    }
}
