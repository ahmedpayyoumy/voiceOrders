<?php

namespace App\Filament\Resources\WebhookEndpoints\Schemas;

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
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
