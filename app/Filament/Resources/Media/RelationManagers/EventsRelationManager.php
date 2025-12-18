<?php

namespace App\Filament\Resources\Media\RelationManagers;

use App\Enum\MediaEventTypeName;
use App\Filament\Resources\MediaEvents\MediaEventResource;
use App\Models\MediaEventType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('media_event_type_id')
                    ->relationship(name: 'mediaEventType', titleAttribute: 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name->value)
                    ->required()
                    ->live(),
                DatePicker::make('occurred_at')
                    ->label('Date')
                    ->required(),
                Textarea::make('comment')
                    ->columnSpanFull()
                    ->required(fn (Get $get): bool => $get('media_event_type_id') == self::commentEventTypeId()),
            ]);
    }

    private static function commentEventTypeId(): ?int
    {
        return once(fn () => MediaEventType::where('name', MediaEventTypeName::COMMENT)->first()?->id);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('mediaEventType.name'),
                TextColumn::make('occurred_at')->label('Date')->date(),
                TextColumn::make('comment')->limit(50),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                // Tables\Actions\AssociateAction::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => MediaEventResource::getUrl('view', ['record' => $record])),
                EditAction::make(),
                // Tables\Actions\DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Tables\Actions\DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
