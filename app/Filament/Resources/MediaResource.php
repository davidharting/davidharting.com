<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\MediaResource\Pages\ListMedia;
use App\Filament\Resources\MediaResource\Pages\CreateMedia;
use App\Filament\Resources\MediaResource\Pages\ViewMedia;
use App\Filament\Resources\MediaResource\Pages\EditMedia;
use App\Filament\Resources\MediaResource\Pages;
use App\Filament\Resources\MediaResource\RelationManagers\EventsRelationManager;
use App\Models\Media;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('media_type_id')
                    ->relationship('mediaType')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name->getLabel())
                    ->required(),
                TextInput::make('year')
                    ->numeric(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('note')
                    ->columnSpanFull(),
                Select::make('creator_id')
                    ->relationship(name: 'creator', titleAttribute: 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mediaType.name')
                    ->sortable(),
                TextColumn::make('year')
                    ->numeric(thousandsSeparator: false)
                    ->sortable(),
                TextColumn::make('title')
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
            EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'create' => CreateMedia::route('/create'),
            'view' => ViewMedia::route('/{record}'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }
}
