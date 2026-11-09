<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Feature tests run against Postgres (database vaultnine_test, see phpunit.xml),
| so check constraints, unique indexes and triggers are exercised for real.
*/

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
