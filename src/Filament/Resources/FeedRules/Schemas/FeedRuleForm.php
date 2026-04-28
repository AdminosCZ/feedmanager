<?php

declare(strict_types=1);

namespace Adminos\Modules\Feedmanager\Filament\Resources\FeedRules\Schemas;

use Adminos\Modules\Feedmanager\Models\FeedRule;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class FeedRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('feedmanager::feedmanager.feed_rules.sections.scope'))
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->label(__('feedmanager::feedmanager.fields.feed_rule_name'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('feed_config_id')
                        ->label(__('feedmanager::feedmanager.fields.feed_config'))
                        ->relationship('feedConfig', 'name')
                        ->preload()
                        ->searchable()
                        ->helperText(__('feedmanager::feedmanager.helpers.feed_rule_feed_config')),
                    Select::make('supplier_id')
                        ->label(__('feedmanager::feedmanager.fields.supplier'))
                        ->relationship('supplier', 'name')
                        ->preload()
                        ->searchable()
                        ->helperText(__('feedmanager::feedmanager.helpers.feed_rule_supplier')),
                ]),

            Section::make(__('feedmanager::feedmanager.feed_rules.sections.condition'))
                ->columns(3)
                ->components([
                    Select::make('field')
                        ->label(__('feedmanager::feedmanager.fields.feed_rule_field'))
                        ->required()
                        ->options(self::fieldOptions())
                        ->native(false),
                    Select::make('condition_op')
                        ->label(__('feedmanager::feedmanager.fields.feed_rule_condition_op'))
                        ->required()
                        ->options(self::conditionOptions())
                        ->default(FeedRule::COND_ALWAYS)
                        ->native(false),
                    TextInput::make('condition_value')
                        ->label(__('feedmanager::feedmanager.fields.feed_rule_condition_value'))
                        ->maxLength(1024),
                ]),

            Section::make(__('feedmanager::feedmanager.feed_rules.sections.action'))
                ->columns(2)
                ->components([
                    Select::make('action')
                        ->label(__('feedmanager::feedmanager.fields.feed_rule_action'))
                        ->required()
                        ->options(self::actionOptions())
                        ->native(false),
                    TextInput::make('action_value')
                        ->label(__('feedmanager::feedmanager.fields.feed_rule_action_value'))
                        ->maxLength(1024)
                        ->helperText(__('feedmanager::feedmanager.helpers.feed_rule_action_value')),
                ]),

            Section::make(__('feedmanager::feedmanager.feed_rules.sections.runtime'))
                ->columns(2)
                ->components([
                    TextInput::make('priority')
                        ->label(__('feedmanager::feedmanager.fields.feed_rule_priority'))
                        ->numeric()
                        ->minValue(0)
                        ->default(100)
                        ->helperText(__('feedmanager::feedmanager.helpers.feed_rule_priority')),
                    Toggle::make('is_active')
                        ->label(__('feedmanager::feedmanager.fields.is_active'))
                        ->default(true),
                ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function fieldOptions(): array
    {
        $names = [
            'name', 'description', 'manufacturer',
            'price', 'price_vat', 'old_price_vat', 'currency',
            'stock_quantity', 'availability',
            'image_url', 'category_text', 'complete_path',
            'ean', 'product_number',
        ];

        return array_combine($names, $names);
    }

    /**
     * @return array<string, string>
     */
    private static function conditionOptions(): array
    {
        return [
            FeedRule::COND_ALWAYS => __('feedmanager::feedmanager.feed_rules.cond.always'),
            FeedRule::COND_EQ => __('feedmanager::feedmanager.feed_rules.cond.eq'),
            FeedRule::COND_NEQ => __('feedmanager::feedmanager.feed_rules.cond.neq'),
            FeedRule::COND_CONTAINS => __('feedmanager::feedmanager.feed_rules.cond.contains'),
            FeedRule::COND_STARTS_WITH => __('feedmanager::feedmanager.feed_rules.cond.starts_with'),
            FeedRule::COND_ENDS_WITH => __('feedmanager::feedmanager.feed_rules.cond.ends_with'),
            FeedRule::COND_GT => __('feedmanager::feedmanager.feed_rules.cond.gt'),
            FeedRule::COND_LT => __('feedmanager::feedmanager.feed_rules.cond.lt'),
            FeedRule::COND_MATCHES => __('feedmanager::feedmanager.feed_rules.cond.matches'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function actionOptions(): array
    {
        return [
            FeedRule::ACTION_SET => __('feedmanager::feedmanager.feed_rules.act.set'),
            FeedRule::ACTION_ADD => __('feedmanager::feedmanager.feed_rules.act.add'),
            FeedRule::ACTION_SUBTRACT => __('feedmanager::feedmanager.feed_rules.act.subtract'),
            FeedRule::ACTION_MULTIPLY => __('feedmanager::feedmanager.feed_rules.act.multiply'),
            FeedRule::ACTION_DIVIDE => __('feedmanager::feedmanager.feed_rules.act.divide'),
            FeedRule::ACTION_REPLACE => __('feedmanager::feedmanager.feed_rules.act.replace'),
            FeedRule::ACTION_PREPEND => __('feedmanager::feedmanager.feed_rules.act.prepend'),
            FeedRule::ACTION_APPEND => __('feedmanager::feedmanager.feed_rules.act.append'),
            FeedRule::ACTION_ROUND => __('feedmanager::feedmanager.feed_rules.act.round'),
            FeedRule::ACTION_REMOVE => __('feedmanager::feedmanager.feed_rules.act.remove'),
        ];
    }
}
