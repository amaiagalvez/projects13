<?php

namespace Projects13\Tests;

abstract class DuskTestCase extends \Basics13\Support\Testing\DuskTestCase
{
    /** @var class-string */
    protected string $seeder = AccessSeeder::class;
}
