<?php

namespace App\Filament\Resources\Words;

use App\Filament\Resources\Words\Pages\CreateWord;
use App\Filament\Resources\Words\Pages\EditWord;
use App\Filament\Resources\Words\Pages\ListWords;
use App\Models\Word;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WordResource extends Resource
{
    protected static ?string $model = Word::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $recordTitleAttribute = 'word';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('word')
                    ->required()
                    ->maxLength(255)
                    ->autocapitalize('none')
                    ->autocomplete(false)
                    ->extraInputAttributes(['autocorrect' => 'off', 'spellcheck' => 'false'])
                    ->rule(fn (?Word $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if (is_string($value) && Word::spelledAs($value, $record) !== null) {
                            $fail('That word is already on the list.');
                        }
                    }),
                Textarea::make('short_definition')
                    ->required()
                    ->helperText('Brief definitions of the senses you care about, separated by semicolons.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('word')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('short_definition')
                    ->lineClamp(2)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable()
                    ->visibleFrom('sm'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWords::route('/'),
            'create' => CreateWord::route('/create'),
            'edit' => EditWord::route('/{record}/edit'),
        ];
    }
}
