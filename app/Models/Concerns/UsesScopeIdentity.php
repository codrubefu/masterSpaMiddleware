<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * pdo_sqlsrv reads the new id with @@IDENTITY, which returns the id inserted
 * by a trigger (e.g. trg_client_audit -> spa_hist.dbo.client_audit) instead
 * of the row's own id. SCOPE_IDENTITY() ignores triggers.
 */
trait UsesScopeIdentity
{
    protected function insertAndSetId(Builder $query, $attributes)
    {
        $base = $query->getQuery();
        $connection = $base->getConnection();

        $sql = $base->getGrammar()->compileInsert($base, $attributes);
        $bindings = $base->cleanBindings(array_values($attributes));

        $row = $connection->selectOne(
            "SET NOCOUNT ON; {$sql}; SELECT CAST(SCOPE_IDENTITY() AS bigint) AS id",
            $bindings
        );

        $this->setAttribute($this->getKeyName(), (int) $row->id);
    }
}
