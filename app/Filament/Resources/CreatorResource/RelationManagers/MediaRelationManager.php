<?php

namespace App\Filament\Resources\CreatorResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\AssociateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('media_type_id')
                    ->relationship('mediaType', 'name')
                    ->required(),
                TextInput::make('year')
                    ->numeric(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('note')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('year')->numeric(thousandsSeparator: ''),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                // Tables\Actions\AttachAction::make(),
                AssociateAction::make()->preloadRecordSelect(),
            ])
            ->recordActions([
                EditAction::make(),
                // Tables\Actions\DetachAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->inverseRelationship('creator');
    }
}
