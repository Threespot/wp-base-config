<?php

namespace Threespot\Wp\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that need WP function stubs.
 *
 * Brain Monkey's setUp/tearDown installs/removes the global Mockery container
 * and the WP function stubs (add_filter, apply_filters, home_url, ...).
 */
abstract class BrainMonkeyTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
