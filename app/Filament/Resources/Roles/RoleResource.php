<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\Role;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Roles';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('manage roles') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Role permissions')
                ->schema([
                    TextInput::make('name')->required()->maxLength(80)->unique(ignoreRecord: true),
                    TextInput::make('description')->maxLength(255),
                    CheckboxList::make('permissions')
                        ->relationship(titleAttribute: 'name')
                        ->columns(['default' => 1, 'md' => 2])
                        ->searchable()
                        ->bulkToggleable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium'),
                TextColumn::make('description')->searchable()->wrap(),
                TextColumn::make('users_count')->counts('users')->label('Users')->sortable(),
                TextColumn::make('permissions_count')->counts('permissions')->label('Permissions'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make()
                    ->visible(static fn (Role $record): bool => $record->name !== 'Super Admin'),
                \Filament\Actions\DeleteAction::make()
                    ->modalHeading('Delete Role')
                    ->modalDescription('Users assigned this role will lose its permissions.')
                    ->visible(static fn (Role $record): bool => $record->name !== 'Super Admin'),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No roles configured');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
