<?php

declare(strict_types=1);

use App\Filament\Resources\SheetFiles\Pages\CreateSheetFile;
use App\Filament\Resources\SheetFiles\Pages\EditSheetFile;
use App\Filament\Resources\SheetFiles\Pages\ListSheetFiles;
use App\Filament\Resources\SheetFiles\RelationManagers\TabsRelationManager;
use App\Jobs\SyncSheetFileJob;
use App\Models\SheetFile;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

it('creates a sheet file from a full Google Sheets URL', function (): void {
    livewire(CreateSheetFile::class)
        ->fillForm([
            'name' => 'Sales',
            'spreadsheet_id' => 'https://docs.google.com/spreadsheets/d/abc-123_XYZ/edit#gid=0',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect(SheetFile::query()->where('name', 'Sales')->value('spreadsheet_id'))->toBe('abc-123_XYZ');
});

it('queues the sync job from the table action', function (): void {
    Queue::fake();
    $file = SheetFile::factory()->create(['name' => 'Orders']);

    livewire(ListSheetFiles::class)
        ->callAction(TestAction::make('syncNow')->table($file))
        ->assertNotified('Sync queued');

    Queue::assertPushed(SyncSheetFileJob::class, fn (SyncSheetFileJob $job): bool => $job->file->is($file));
});

it('rejects protected or invalid mapping target tables', function (string $targetTable): void {
    $file = SheetFile::factory()->create();

    livewire(TabsRelationManager::class, [
        'ownerRecord' => $file,
        'pageClass' => EditSheetFile::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), data: [
            'tab_name' => 'Orders',
            'header_row' => 1,
            'is_active' => true,
            'mappings' => [[
                'source_column' => 'Order ID',
                'target_table' => $targetTable,
                'target_column' => 'order_id',
                'column_type' => 'integer',
                'is_unique_key' => true,
            ]],
        ])
        ->assertHasFormErrors(['mappings.0.target_table']);
})->with([
    'protected table' => 'users',
    'invalid identifier' => 'Sales Orders',
]);

it('rejects a text mapping marked as a unique key', function (): void {
    $file = SheetFile::factory()->create();

    livewire(TabsRelationManager::class, [
        'ownerRecord' => $file,
        'pageClass' => EditSheetFile::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), data: [
            'tab_name' => 'Orders',
            'header_row' => 1,
            'is_active' => true,
            'mappings' => [[
                'source_column' => 'Description',
                'target_table' => 'orders',
                'target_column' => 'description',
                'column_type' => 'text',
                'is_unique_key' => true,
            ]],
        ])
        ->assertHasFormErrors(['mappings']);
});

it('creates the requested target column identifiers from loaded headers', function (string $header, string $targetColumn): void {
    expect(TabsRelationManager::targetColumnFromHeader($header))->toBe($targetColumn);
})->with([
    'order id' => ['Order ID', 'order_id'],
    'product name' => ['Product Name', 'product_name'],
    'price with symbols' => ['Price ($)', 'price'],
]);
