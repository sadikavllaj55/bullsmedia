<?php

declare(strict_types=1);

namespace App\Filament\Resources\SheetFiles\RelationManagers;

use App\Contracts\SpreadsheetReader;
use App\Enums\ColumnType;
use App\Models\SheetFile;
use App\Support\SyncIdentifier;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\ClosureValidationRule;
use Throwable;

class TabsRelationManager extends RelationManager
{
    protected static string $relationship = 'sheetTabs';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tab_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('header_row')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                TextInput::make('default_target_table')
                    ->label('Default target table')
                    ->default('sync_data')
                    ->dehydrated(false)
                    ->required()
                    ->rules([
                        new ClosureValidationRule(function (string $attribute, mixed $value, Closure $fail): void {
                            if (! is_string($value)
                                || ! SyncIdentifier::isValid($value)
                                || SyncIdentifier::isProtected($value)) {
                                $fail('Enter a valid, unprotected target table identifier.');

                                return;
                            }

                            if (! self::isAllowedExistingTable($value)) {
                                $fail('This existing table is not managed by the sync service.');
                            }
                        }),
                    ]),
                Toggle::make('is_active')
                    ->default(true),
                Actions::make([
                    Action::make('loadHeaders')
                        ->label('Load headers')
                        ->action(function (Get $schemaGet, Set $schemaSet, SpreadsheetReader $spreadsheetReader): void {
                            $tabName = trim((string) ($schemaGet('tab_name') ?? ''));
                            $headerRow = max(1, (int) ($schemaGet('header_row') ?? 1));
                            $sheetFile = $this->getOwnerRecord();

                            if ($tabName === '' || ! $sheetFile instanceof SheetFile) {
                                Notification::make()
                                    ->title('Could not load headers')
                                    ->body('Enter a tab name before loading headers.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                $rows = $spreadsheetReader->getTabValues($sheetFile->spreadsheet_id, $tabName);
                            } catch (Throwable $exception) {
                                Notification::make()
                                    ->title('Could not load headers')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $headers = $rows[$headerRow - 1] ?? null;

                            if (! is_array($headers)) {
                                Notification::make()
                                    ->title('Could not load headers')
                                    ->body('The selected header row was not found in the spreadsheet tab.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $mappings = $schemaGet('mappings');
                            $mappings = is_array($mappings) ? $mappings : [];
                            $targetTable = trim((string) ($schemaGet('default_target_table') ?? ''));

                            if ($targetTable === '') {
                                $targetTable = self::targetTableFromTabName($tabName);
                                $schemaSet('default_target_table', $targetTable);
                            }

                            $mappings = self::mergeHeaderMappings($mappings, $headers, $targetTable);

                            if ($mappings === []) {
                                Notification::make()
                                    ->title('No headers found')
                                    ->body('The selected row does not contain any column headers.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $schemaSet('mappings', $mappings);

                            Notification::make()
                                ->title('Headers loaded')
                                ->success()
                                ->send();
                        }),
                ]),
                Repeater::make('mappings')
                    ->relationship(name: 'columnMappings')
                    ->rules([
                        new ClosureValidationRule(function (string $attribute, mixed $value, Closure $fail): void {
                            if (! is_array($value)) {
                                return;
                            }

                            foreach ($value as $mapping) {
                                if (is_array($mapping)
                                    && is_string($mapping['target_column'] ?? null)
                                    && SyncIdentifier::isReservedColumn($mapping['target_column'])) {
                                    $fail('System columns cannot be mapped.');

                                    return;
                                }

                                if (is_array($mapping)
                                    && ($mapping['column_type'] ?? null) === ColumnType::Text->value
                                    && (bool) ($mapping['is_unique_key'] ?? false)) {
                                    $fail('Text columns cannot be unique keys; use string instead.');

                                    return;
                                }
                            }

                            $seenTargetColumns = [];

                            foreach ($value as $mapping) {
                                if (! is_array($mapping)
                                    || ! is_string($mapping['target_table'] ?? null)
                                    || ! is_string($mapping['target_column'] ?? null)) {
                                    continue;
                                }

                                $key = $mapping['target_table'].'.'.$mapping['target_column'];

                                if (isset($seenTargetColumns[$key])) {
                                    $fail('Target columns must be unique within each target table.');

                                    return;
                                }

                                $seenTargetColumns[$key] = true;
                            }
                        }),
                    ])
                    ->schema([
                        TextInput::make('source_column')
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('target_table')
                            ->required()
                            ->live(onBlur: true)
                            ->rules([
                                new ClosureValidationRule(function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! is_string($value) || ! SyncIdentifier::isValid($value)) {
                                        $fail('Enter a lowercase table identifier using letters, numbers, and underscores.');

                                        return;
                                    }

                                    if (SyncIdentifier::isProtected($value)) {
                                        $fail('This table name is protected.');

                                        return;
                                    }

                                    if (! self::isAllowedExistingTable($value)) {
                                        $fail('This existing table is not managed by the sync service.');
                                    }
                                }),
                            ]),
                        TextInput::make('target_column')
                            ->required()
                            ->live(onBlur: true)
                            ->rules([
                                new ClosureValidationRule(function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! is_string($value)
                                        || ! SyncIdentifier::isValid($value)
                                        || SyncIdentifier::isReservedColumn($value)) {
                                        $fail('Enter a lowercase column identifier using letters, numbers, and underscores.');
                                    }
                                }),
                            ]),
                        Select::make('column_type')
                            ->options(ColumnType::class)
                            ->required(),
                        Toggle::make('is_unique_key')
                            ->default(false),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => isset($state['source_column'])
                        ? ($state['source_column'].' → '.($state['target_table'] ?? '').'.'.($state['target_column'] ?? ''))
                        : null),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tab_name')
            ->columns([
                TextColumn::make('tab_name')
                    ->searchable(),
                TextColumn::make('header_row')
                    ->label('Header row'),
                ToggleColumn::make('is_active')
                    ->label('Active'),
                TextColumn::make('column_mappings_count')
                    ->label('Mappings')
                    ->counts('columnMappings'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function targetColumnFromHeader(string $header): string
    {
        $column = Str::of($header)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        if ($column === '') {
            $column = 'column';
        }

        if (ctype_digit($column[0])) {
            $column = 'column_'.$column;
        }

        return substr($column, 0, 64);
    }

    /**
     * @param  array<array-key, mixed>  $existingMappings
     * @param  array<int, mixed>  $headers
     * @return array<array-key, mixed>
     */
    public static function mergeHeaderMappings(array $existingMappings, array $headers, string $targetTable): array
    {
        $existingSources = [];
        $usedTargetColumns = [];

        foreach ($existingMappings as $mapping) {
            if (! is_array($mapping)) {
                continue;
            }

            $source = self::normalizeHeader($mapping['source_column'] ?? null);
            if ($source !== '') {
                $existingSources[$source] = true;
            }

            if (is_string($mapping['target_table'] ?? null)
                && is_string($mapping['target_column'] ?? null)) {
                $key = $mapping['target_table'].'.'.$mapping['target_column'];
                $usedTargetColumns[$key] = true;
            }
        }

        foreach ($headers as $header) {
            if (! is_string($header) || trim($header) === '') {
                continue;
            }

            $sourceColumn = trim($header);
            $normalizedSource = self::normalizeHeader($sourceColumn);

            if (isset($existingSources[$normalizedSource])) {
                continue;
            }

            $baseTargetColumn = self::targetColumnFromHeader($sourceColumn);
            $targetColumn = $baseTargetColumn;
            $suffix = 2;

            while (isset($usedTargetColumns[$targetTable.'.'.$targetColumn])) {
                $targetColumn = substr($baseTargetColumn, 0, 57).'_'.$suffix;
                $suffix++;
            }

            $existingMappings[] = [
                'source_column' => $sourceColumn,
                'target_table' => $targetTable,
                'target_column' => $targetColumn,
                'column_type' => ColumnType::String->value,
                'is_unique_key' => false,
            ];

            $existingSources[$normalizedSource] = true;
            $usedTargetColumns[$targetTable.'.'.$targetColumn] = true;
        }

        return $existingMappings;
    }

    private static function targetTableFromTabName(string $tabName): string
    {
        return 'sync_'.substr(self::targetColumnFromHeader($tabName), 0, 59);
    }

    private static function normalizeHeader(mixed $header): string
    {
        if (! is_string($header)) {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($header)) ?? trim($header);

        return mb_strtolower($normalized);
    }

    private static function isAllowedExistingTable(string $table): bool
    {
        return ! DatabaseSchema::hasTable($table)
            || collect(['id', 'sheet_file_id', 'created_at', 'updated_at'])
                ->every(fn (string $column): bool => DatabaseSchema::hasColumn($table, $column));
    }
}
