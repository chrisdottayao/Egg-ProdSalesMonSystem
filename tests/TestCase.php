<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests run on in-memory SQLite; production is MySQL. The dashboard
        // uses MySQL's GREATEST() and FIELD(), which SQLite lacks — register
        // equivalents so dashboard pages can be tested at all.
        if (DB::connection()->getDriverName() === 'sqlite') {
            $pdo = DB::connection()->getPdo();
            $pdo->sqliteCreateFunction('GREATEST', fn (...$args) => max($args));
            $pdo->sqliteCreateFunction('FIELD', function ($needle, ...$list) {
                $i = array_search($needle, $list);

                return $i === false ? 0 : $i + 1;
            });
        }
    }
}
