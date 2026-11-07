<?php

declare(strict_types=1);

namespace App\Filament\Resources\SheetFiles\Schemas;

use Filament\Schemas\Schema;

class SheetFileInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }
}
