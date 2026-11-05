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
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->rule(fn (?Word $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $taken = Word::query()
                            ->named($value)
                            ->when($record, fn (Builder $query) => $query->whereKeyNot($record->id))
                            ->exists();

                        if ($taken) {
                            $fail('That word is already on the list.');
                        }
                    }),
                Textarea::make('short_definition')
                    ->helperText('A plain-words definition to quiz against.')
                    ->columnSpanFull(),
                Repeater::make('senses')
                    ->helperText('The dictionary senses you are learning this word in.')
                    ->schema([
                        TextInput::make('part_of_speech')
                            ->maxLength(50),
                        Textarea::make('definition')
                            ->required(),
                        Repeater::make('examples')
                            ->simple(Textarea::make('example')->required())
                            ->defaultItems(0),
                    ])
                    ->defaultItems(0)
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
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereRaw('word ilike ?', ['%'.$search.'%']))
                    ->sortable(),
                TextColumn::make('short_definition')
                    ->lineClamp(2)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable(),
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
