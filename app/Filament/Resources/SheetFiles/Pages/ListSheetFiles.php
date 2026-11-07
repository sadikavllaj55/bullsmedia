<?php

declare(strict_types=1);

namespace App\Filament\Resources\SheetFiles\Pages;

use App\Filament\Resources\SheetFiles\SheetFileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSheetFiles extends ListRecords
{
    protected static string $resource = SheetFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
