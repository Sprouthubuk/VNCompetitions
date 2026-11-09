<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Ledger rows are written once. The database rejects UPDATE and DELETE too
 * (see the *_append_only triggers); this fails earlier with a clearer error.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(function (self $model): never {
            throw new LogicException(class_basename($model).' is append-only and cannot be updated.');
        });

        static::deleting(function (self $model): never {
            throw new LogicException(class_basename($model).' is append-only and cannot be deleted.');
        });
    }
}
