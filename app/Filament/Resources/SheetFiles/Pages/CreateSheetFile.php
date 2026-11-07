<?php

declare(strict_types=1);

namespace App\Filament\Resources\SheetFiles\Pages;

use App\Filament\Resources\SheetFiles\SheetFileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSheetFile extends CreateRecord
{
    protected static string $resource = SheetFileResource::class;
}
