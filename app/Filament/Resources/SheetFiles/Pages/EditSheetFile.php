<?php

declare(strict_types=1);

namespace App\Filament\Resources\SheetFiles\Pages;

use App\Filament\Resources\SheetFiles\SheetFileResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSheetFile extends EditRecord
{
    protected static string $resource = SheetFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
