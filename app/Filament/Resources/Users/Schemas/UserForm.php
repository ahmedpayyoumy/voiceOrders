<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('monthly_quota')
                    ->required()
                    ->numeric()
                    ->default(100),
                TextInput::make('used_this_month')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('quota_reset_at'),
            ]);
    }
}
