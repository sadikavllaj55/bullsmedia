<?php

declare(strict_types=1);

namespace App\Filament\Resources\SheetFiles\Pages;

use App\Filament\Resources\SheetFiles\SheetFileResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSheetFile extends ViewRecord
{
    protected static string $resource = SheetFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
