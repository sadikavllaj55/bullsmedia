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
use Filament\Tables\Table;
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

                            $mappings = [];

                            foreach ($headers as $header) {
                                if (! is_string($header) || trim($header) === '') {
                                    continue;
                                }

                                $mappings[] = [
                                    'source_column' => trim($header),
                                    'target_table' => '',
                                    'target_column' => self::targetColumnFromHeader($header),
                                    'column_type' => ColumnType::String->value,
                                    'is_unique_key' => false,
                                ];
                            }

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
                                    && ($mapping['column_type'] ?? null) === ColumnType::Text->value
                                    && (bool) ($mapping['is_unique_key'] ?? false)) {
                                    $fail('Text columns cannot be unique keys; use string instead.');

                                    return;
                                }
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
                                    }
                                }),
                            ]),
                        TextInput::make('target_column')
                            ->required()
                            ->live(onBlur: true)
                            ->rules([
                                new ClosureValidationRule(function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! is_string($value) || ! SyncIdentifier::isValid($value)) {
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
        return Str::of($header)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
