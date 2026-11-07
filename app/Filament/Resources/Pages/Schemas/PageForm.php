<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Support\StoreWebImage;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                    ->columnSpanFull()
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory(fn ($record) => $record
                        ? 'pages/'.$record->slug
                        : 'pages/'.now()->toDateString()
                    )
                    ->fileAttachmentsAcceptedFileTypes(StoreWebImage::MIME_TYPES)
                    ->fileAttachmentsMaxSize(StoreWebImage::MAX_KILOBYTES)
                    ->saveUploadedFileAttachmentUsing(fn (TemporaryUploadedFile $file, MarkdownEditor $component): ?string => app(StoreWebImage::class)->handle(
                        $file,
                        $component->getFileAttachmentsDiskName(),
                        $component->getFileAttachmentsDirectory(),
                    )),
            ]);
    }
}
