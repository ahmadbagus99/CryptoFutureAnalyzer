<?php

namespace Tests\Unit;

use App\Services\FvgAnalyzer;
use PHPUnit\Framework\TestCase;

class FvgAnalyzerTest extends TestCase
{
    public function test_detects_bullish_fvg(): void
    {
        $candles = [
            ['open_time' => 1, 'open' => 99, 'high' => 100, 'low' => 98, 'close' => 99.5, 'volume' => 1],
            ['open_time' => 2, 'open' => 99.5, 'high' => 101, 'low' => 99, 'close' => 100.5, 'volume' => 1],
            ['open_time' => 3, 'open' => 100.5, 'high' => 104, 'low' => 102, 'close' => 103, 'volume' => 1],
        ];

        $a = new FvgAnalyzer;
        $fvgs = $a->detect($candles);

        $this->assertNotEmpty($fvgs);
        $this->assertSame('bullish', $fvgs[0]['direction']);
        $this->assertEquals(100.0, $fvgs[0]['zone_low']);
        $this->assertEquals(102.0, $fvgs[0]['zone_high']);
    }

    public function test_detects_bearish_fvg(): void
    {
        $candles = [
            ['open_time' => 1, 'open' => 102, 'high' => 105, 'low' => 101, 'close' => 104, 'volume' => 1],
            ['open_time' => 2, 'open' => 104, 'high' => 104, 'low' => 100, 'close' => 101, 'volume' => 1],
            ['open_time' => 3, 'open' => 101, 'high' => 99, 'low' => 98, 'close' => 98.5, 'volume' => 1],
        ];

        $a = new FvgAnalyzer;
        $fvgs = $a->detect($candles);

        $this->assertNotEmpty($fvgs);
        $bearish = null;
        foreach ($fvgs as $f) {
            if ($f['direction'] === 'bearish') {
                $bearish = $f;
                break;
            }
        }
        $this->assertNotNull($bearish);
        $this->assertEquals(99.0, $bearish['zone_low']);
        $this->assertEquals(101.0, $bearish['zone_high']);
    }
}
