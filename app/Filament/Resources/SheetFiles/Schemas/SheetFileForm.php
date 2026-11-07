<?php

declare(strict_types=1);

namespace App\Filament\Resources\SheetFiles\Schemas;

use App\Models\SheetFile;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\ClosureValidationRule;

class SheetFileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('spreadsheet_id')
                    ->label('Spreadsheet ID or URL')
                    ->required()
                    ->maxLength(2048)
                    ->rules(fn (?SheetFile $record): array => [
                        new ClosureValidationRule(function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $spreadsheetId = self::normalizeSpreadsheetId(is_string($value) ? $value : null);

                            if ($spreadsheetId === null || preg_match('/^[a-zA-Z0-9-_]+$/', $spreadsheetId) !== 1) {
                                $fail('Enter a Google Sheets URL or spreadsheet ID.');

                                return;
                            }

                            $query = SheetFile::query()->where('spreadsheet_id', $spreadsheetId);

                            if ($record !== null) {
                                $query->whereKeyNot($record->getKey());
                            }

                            if ($query->exists()) {
                                $fail('This spreadsheet is already connected.');
                            }
                        }),
                    ])
                    ->dehydrateStateUsing(fn (?string $state): ?string => self::normalizeSpreadsheetId($state)),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    private static function normalizeSpreadsheetId(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $value, $matches) === 1) {
            return $matches[1];
        }

        return trim($value);
    }
}
