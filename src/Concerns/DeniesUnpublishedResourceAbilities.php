<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * The abilities no resource in this package publishes, forced closed by name.
 *
 * Naming them is the whole point. A missing policy is permissive; a policy that
 * exists but lacks the method asked about is *also* permissive, because
 * Filament's `get_authorization_response()` falls through to allow when the
 * method is absent. "We did not write a delete policy" is therefore not "delete
 * is off" — it is "delete is on and nobody decided".
 *
 * Everything this panel shows is evidence. A privacy request is the record that
 * somebody asked and what each participant answered; a claim attempt is the
 * record that somebody tried; a share is the record that a link existed and when
 * it stopped working. Deleting any of them through a panel button destroys the
 * only account of an event that has already happened — and in a module whose
 * whole purpose is that "we erased you" must be a claim somebody can check, a
 * deletable audit trail is worse than no audit trail.
 *
 * Abilities a resource *does* publish are deliberately absent from this list:
 * they fall through to Filament's policy lookup, so a host shipping a gate
 * policy for these models still governs who may read.
 */
trait DeniesUnpublishedResourceAbilities
{
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canReorder(): bool
    {
        return false;
    }

    public static function canReplicate(Model $record): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }
}
