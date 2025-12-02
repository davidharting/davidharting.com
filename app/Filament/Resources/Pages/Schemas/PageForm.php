<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug'),
                TextInput::make('title')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->default(false),
                MarkdownEditor::make('markdown_content')
                    ->label('Content')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
