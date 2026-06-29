<?php

namespace App\Filament\Resources\WebhookEndpoints\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WebhookEndpointForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('url')
                    ->url()
                    ->required(),
                TextInput::make('method')
                    ->required()
                    ->default('POST'),
                TextInput::make('headers'),
                Textarea::make('transform_prompt')
                    ->helperText('Describe how to parse the transcript into a structured API request. E.g.: "Extract the product_id and action from the transcript. Return JSON with action and product_id keys."')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
