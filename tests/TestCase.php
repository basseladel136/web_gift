<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        putenv('DB_DATABASE=WebGift_testing');
        
        parent::setUp();
    }
}