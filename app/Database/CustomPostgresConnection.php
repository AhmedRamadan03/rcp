<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;

class CustomPostgresConnection extends BasePostgresConnection
{
    /**
     * Prepare the query bindings for execution.
     *
     * @param  array  $bindings
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            // Transform all DateTimeInterface instances date string.
            if ($value instanceof \DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif (is_bool($value)) {
                // FORCE BOOLEAN TO STRING 'true'/'false'
                // Standard Laravel casts this to (int) which breaks PgBouncer/Emulation
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return $bindings;
    }
}
