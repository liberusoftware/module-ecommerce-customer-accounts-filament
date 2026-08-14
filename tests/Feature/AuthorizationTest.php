<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\ClaimAttempts\ClaimAttemptResource;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\PrivacyRequestResource;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\RelationManagers\ParticipantsRelationManager;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\RelationManagers\SavedListItemsRelationManager;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\RelationManagers\SavedListSharesRelationManager;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\SavedListResource;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;

/**
 * The ability matrix, asserted by name.
 *
 * A missing policy is permissive and a present policy missing the method asked
 * about is *also* permissive, because Filament falls through to allow. So the
 * only way to know an ability is closed is to ask it and be told no. These tests
 * are the "and be told no" half.
 */
// Nested rows deliberately: `[ClassA::class, ClassB::class]` is PHP's callable
// array syntax, and Pest calls it instead of iterating it.
$resources = [
    [PrivacyRequestResource::class],
    [ClaimAttemptResource::class],
    [SavedListResource::class],
];

$managers = [
    [ParticipantsRelationManager::class],
    [SavedListItemsRelationManager::class],
    [SavedListSharesRelationManager::class],
];

it('closes every destructive resource ability by name', function (string $resource): void {
    $record = new PrivacyRequest();

    expect($resource::canDelete($record))->toBeFalse()
        ->and($resource::canDeleteAny())->toBeFalse()
        ->and($resource::canForceDelete($record))->toBeFalse()
        ->and($resource::canForceDeleteAny())->toBeFalse()
        ->and($resource::canReorder())->toBeFalse()
        ->and($resource::canReplicate($record))->toBeFalse()
        ->and($resource::canRestore($record))->toBeFalse()
        ->and($resource::canRestoreAny())->toBeFalse();
})->with($resources);

it('publishes no create and no edit anywhere, because everything here is evidence', function (string $resource): void {
    $record = new PrivacyRequest();

    // A case, a claim attempt and a saved list are all records of something
    // that already happened, and a deletable audit trail in this module is
    // worse than none at all.
    expect($resource::canCreate())->toBeFalse()
        ->and($resource::canEdit($record))->toBeFalse();
})->with($resources);

it('closes every relation-manager ability by name, including associate and dissociate', function (string $manager): void {
    /** @var object $instance */
    $instance = new $manager();
    $record = new PrivacyRequest();

    // canAssociate and canDissociate are live on a hasMany and default open.
    // Dissociating a participant from its case is the button that would make a
    // partial case look complete.
    expect($instance->canAssociate())->toBeFalse()
        ->and($instance->canDissociate($record))->toBeFalse()
        ->and($instance->canDissociateAny())->toBeFalse()
        ->and($instance->canAttach())->toBeFalse()
        ->and($instance->canDetach($record))->toBeFalse()
        ->and($instance->canDetachAny())->toBeFalse()
        ->and($instance->canCreate())->toBeFalse()
        ->and($instance->canEdit($record))->toBeFalse()
        ->and($instance->canDelete($record))->toBeFalse()
        ->and($instance->canDeleteAny())->toBeFalse()
        ->and($instance->canForceDelete($record))->toBeFalse()
        ->and($instance->canForceDeleteAny())->toBeFalse()
        ->and($instance->canReorder())->toBeFalse()
        ->and($instance->canReplicate($record))->toBeFalse()
        ->and($instance->canRestore($record))->toBeFalse()
        ->and($instance->canRestoreAny())->toBeFalse()
        ->and($instance->canView($record))->toBeFalse()
        // The one ability published, stated rather than inherited.
        ->and($instance->canViewAny())->toBeTrue();
})->with($managers);

it('names no policy method after a Filament relation-manager ability', function (): void {
    // A subclass method wins over a trait's, so a method named for an ability
    // would silently reopen it. Nothing outside the two Concerns defines one.
    $reopened = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Concerns/')) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        foreach (['canAssociate', 'canDissociate', 'canAttach', 'canDetach', 'canRestore', 'canForceDelete', 'canDelete'] as $ability) {
            if (preg_match('/function\s+'.$ability.'\s*\(/', $source) === 1) {
                $reopened[] = basename($file->getPathname()).'::'.$ability;
            }
        }
    }

    expect($reopened)->toBe([]);
});

it('resolves the record model for every resource, because Filament has no model-less resource', function (string $resource): void {
    expect($resource::getModel())->toBeString()
        ->and(is_subclass_of($resource::getModel(), Model::class))->toBeTrue();
})->with($resources);
