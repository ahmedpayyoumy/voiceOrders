<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                Textarea::make('transcript')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('parsed_data'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('response_log'),
            ]);
    }
}
