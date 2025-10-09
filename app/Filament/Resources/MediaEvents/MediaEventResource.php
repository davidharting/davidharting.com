<?php

namespace App\Filament\Resources\MediaEvents;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\MediaEvents\Pages\ListMediaEvents;
use App\Filament\Resources\MediaEvents\Pages\CreateMediaEvent;
use App\Filament\Resources\MediaEvents\Pages\ViewMediaEvent;
use App\Filament\Resources\MediaEvents\Pages\EditMediaEvent;
use App\Filament\Resources\MediaEventResource\Pages;
use App\Models\MediaEvent;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MediaEventResource extends Resource
{
    protected static ?string $model = MediaEvent::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('media_event_type_id')
                    ->relationship(name: 'mediaEventType', titleAttribute: 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name->value)
                    ->required(),
                DatePicker::make('occurred_at')
                    ->label('Date')
                    ->required(),
                Select::make('media_id')
                    ->relationship('media', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('comment')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('mediaEventType.name')
                    ->sortable(),
                TextColumn::make('media.title')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('occurred_at')
                    ->date()
                    ->label('Date')
                    ->sortable(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaEvents::route('/'),
            'create' => CreateMediaEvent::route('/create'),
            'view' => ViewMediaEvent::route('/{record}'),
            'edit' => EditMediaEvent::route('/{record}/edit'),
        ];
    }
}
