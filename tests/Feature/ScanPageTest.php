<?php

namespace Tests\Feature;

use Tests\TestCase;

class ScanPageTest extends TestCase
{
    public function test_scan_page_loads_without_run(): void
    {
        $response = $this->get('/scan');

        $response->assertStatus(200);
    }
}
