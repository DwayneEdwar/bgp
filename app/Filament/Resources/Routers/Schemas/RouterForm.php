<?php

namespace App\Filament\Resources\Routers\Schemas;

use App\Models\Router;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class RouterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Router connection')
                    ->description('Store an encrypted credential for user-triggered RouterOS API requests.')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->string()
                            ->maxLength(255)
                            ->autocomplete(false),
                        TextInput::make('host')
                            ->required()
                            ->string()
                            ->maxLength(255)
                            ->ipv4()
                            ->rules(fn (Get $get, ?Router $record): array => [
                                Rule::unique('routers', 'host')
                                    ->where(fn (Builder $query): Builder => $query->where('port', (int) ($get('port') ?: 8728)))
                                    ->ignore($record?->getKey()),
                            ])
                            ->validationAttribute('host')
                            ->helperText('Enter the router IPv4 address. A host and port pair can only be registered once.'),
                        TextInput::make('port')
                            ->required()
                            ->numeric()
                            ->rules(['integer', 'between:1,65535'])
                            ->default(8728)
                            ->minValue(1)
                            ->maxValue(65535)
                            ->helperText('API SSL commonly uses 8729. Custom ports are preserved exactly.'),
                        Select::make('connection_type')
                            ->options([
                                'api' => 'API',
                                'api_ssl' => 'API SSL',
                            ])
                            ->default('api')
                            ->required()
                            ->native(false)
                            ->helperText('API SSL uses TLS; configure the port enabled on the router.'),
                        TextInput::make('username')
                            ->required()
                            ->string()
                            ->maxLength(255)
                            ->autocomplete(false),
                        TextInput::make('password')
                            ->label('API password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->string()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->autocomplete('new-password')
                            ->helperText('Encrypted at rest. Leave blank when editing to retain the existing password.'),
                    ]),
            ]);
    }
}
