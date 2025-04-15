<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaEventResource\Pages;
use App\Models\MediaEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MediaEventResource extends Resource
{
    protected static ?string $model = MediaEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('media_event_type_id')
                    ->relationship(name: 'mediaEventType', titleAttribute: 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name->value)
                    ->required(),
                Forms\Components\DatePicker::make('occurred_at')
                    ->label('Date')
                    ->required(),
                Forms\Components\Select::make('media_id')
                    ->relationship('media', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Textarea::make('comment')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('mediaEventType.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('media.title')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('occurred_at')
                    ->date()
                    ->label('Date')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListMediaEvents::route('/'),
            'create' => Pages\CreateMediaEvent::route('/create'),
            'view' => Pages\ViewMediaEvent::route('/{record}'),
            'edit' => Pages\EditMediaEvent::route('/{record}/edit'),
        ];
    }
}
