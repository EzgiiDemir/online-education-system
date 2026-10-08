<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens/narrows the value list of an "enum" column from a migration.
 *
 * Laravel models enum() on PostgreSQL as varchar(255) plus a CHECK
 * constraint, but ->change() then compiles to
 *
 *     ALTER TABLE t ALTER COLUMN c TYPE varchar(255) CHECK (...)
 *
 * which Postgres rejects outright -- ALTER COLUMN TYPE takes no inline
 * CHECK. The value list lives in a separate, conventionally named
 * constraint (<table>_<column>_check), so on Postgres the honest edit is
 * to swap that constraint rather than restate the column type. Every other
 * driver keeps the ordinary ->change() path.
 */
class EnumConstraint
{
    /**
     * @param  array<int, string>  $values
     */
    public static function redefine(string $table, string $column, array $values, ?string $default = null): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table($table, function (Blueprint $t) use ($column, $values, $default) {
                $definition = $t->enum($column, $values);
                if ($default !== null) {
                    $definition->default($default);
                }
                $definition->change();
            });

            return;
        }

        $pdo = DB::getPdo();
        $constraint = $table.'_'.$column.'_check';
        $list = implode(', ', array_map(fn (string $value) => $pdo->quote($value), $values));

        DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', self::id($table), self::id($constraint)));
        DB::statement(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s::text IN (%s))',
            self::id($table), self::id($constraint), self::id($column), $list
        ));

        if ($default !== null) {
            DB::statement(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s SET DEFAULT %s',
                self::id($table), self::id($column), $pdo->quote($default)
            ));
        }
    }

    /** Quote an identifier; migrations only ever pass literals, but be strict anyway. */
    private static function id(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
}
