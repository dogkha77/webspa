<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait SchemaSpa
{
    protected function taoCauTrucSpa(): void
    {
        DB::connection()->getPdo()->sqliteCreateFunction('FLOOR', static fn ($value) => floor((float) $value), 1);
        $sql = file_get_contents(__DIR__.'/../Fixtures/spa.sql');
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
            if (preg_match('/CREATE TABLE (\w+)/', $statement, $match) && ! Schema::hasTable($match[1])) {
                DB::unprepared($statement);
            }
        }
        if (! Schema::hasTable('de_nghi_mua_goi')) {
            (require database_path('migrations/2026_11_19_000001_create_de_nghi_mua_goi_table.php'))->up();
        }
    }
}
