<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CreatorResource\Pages\ListCreators;
use App\Filament\Resources\CreatorResource\Pages\CreateCreator;
use App\Filament\Resources\CreatorResource\Pages\ViewCreator;
use App\Filament\Resources\CreatorResource\Pages\EditCreator;
use App\Filament\Resources\CreatorResource\Pages;
use App\Filament\Resources\CreatorResource\RelationManagers\MediaRelationManager;
use App\Models\Creator;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CreatorResource extends Resource
{
    protected static ?string $model = Creator::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MediaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreators::route('/'),
            'create' => CreateCreator::route('/create'),
            'view' => ViewCreator::route('/{record}'),
            'edit' => EditCreator::route('/{record}/edit'),
        ];
    }
}
