<?php

declare(strict_types=1);

namespace App\Filament\Resources\SheetFiles;

use App\Filament\Resources\SheetFiles\Pages\CreateSheetFile;
use App\Filament\Resources\SheetFiles\Pages\EditSheetFile;
use App\Filament\Resources\SheetFiles\Pages\ListSheetFiles;
use App\Filament\Resources\SheetFiles\Pages\ViewSheetFile;
use App\Filament\Resources\SheetFiles\RelationManagers\SyncLogsRelationManager;
use App\Filament\Resources\SheetFiles\RelationManagers\TabsRelationManager;
use App\Filament\Resources\SheetFiles\Schemas\SheetFileForm;
use App\Filament\Resources\SheetFiles\Schemas\SheetFileInfolist;
use App\Filament\Resources\SheetFiles\Tables\SheetFilesTable;
use App\Models\SheetFile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SheetFileResource extends Resource
{
    protected static ?string $model = SheetFile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Sync';

    protected static ?string $navigationLabel = 'Sheet Files';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SheetFileForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SheetFileInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SheetFilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TabsRelationManager::class,
            SyncLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSheetFiles::route('/'),
            'create' => CreateSheetFile::route('/create'),
            'view' => ViewSheetFile::route('/{record}'),
            'edit' => EditSheetFile::route('/{record}/edit'),
        ];
    }
}
