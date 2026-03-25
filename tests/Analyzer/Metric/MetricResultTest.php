<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\Metric;

use PhpQuality\Analyzer\Metric\MetricResult;
use PHPUnit\Framework\TestCase;

class MetricResultTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $result = new MetricResult(
            name: 'Cyclomatic Complexity',
            value: 5.5,
            unit: '',
            details: ['min' => 1, 'max' => 15],
            rating: 'B'
        );

        $this->assertSame('Cyclomatic Complexity', $result->name);
        $this->assertSame(5.5, $result->value);
        $this->assertSame('', $result->unit);
        $this->assertSame(['min' => 1, 'max' => 15], $result->details);
        $this->assertSame('B', $result->rating);
    }

    public function testDefaultValues(): void
    {
        $result = new MetricResult(
            name: 'Test',
            value: 100
        );

        $this->assertSame('', $result->unit);
        $this->assertSame([], $result->details);
        $this->assertNull($result->rating);
    }

    public function testToArray(): void
    {
        $result = new MetricResult(
            name: 'MI',
            value: 75.5,
            unit: '%',
            details: ['raw' => 80.2],
            rating: 'B'
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('unit', $array);
        $this->assertArrayHasKey('details', $array);
        $this->assertArrayHasKey('rating', $array);

        $this->assertSame('MI', $array['name']);
        $this->assertSame(75.5, $array['value']);
    }

    /**
     * @dataProvider goodRatingProvider
     */
    public function testIsGood(string $rating, bool $expected): void
    {
        $result = new MetricResult('Test', 100, '', [], $rating);

        $this->assertSame($expected, $result->isGood());
    }

    public static function goodRatingProvider(): array
    {
        return [
            ['A', true],
            ['B', true],
            ['C', false],
            ['D', false],
            ['F', false],
        ];
    }

    /**
     * @dataProvider warningRatingProvider
     */
    public function testIsWarning(string $rating, bool $expected): void
    {
        $result = new MetricResult('Test', 100, '', [], $rating);

        $this->assertSame($expected, $result->isWarning());
    }

    public static function warningRatingProvider(): array
    {
        return [
            ['A', false],
            ['B', false],
            ['C', true],
            ['D', false],
            ['F', false],
        ];
    }

    /**
     * @dataProvider badRatingProvider
     */
    public function testIsBad(string $rating, bool $expected): void
    {
        $result = new MetricResult('Test', 100, '', [], $rating);

        $this->assertSame($expected, $result->isBad());
    }

    public static function badRatingProvider(): array
    {
        return [
            ['A', false],
            ['B', false],
            ['C', false],
            ['D', true],
            ['F', true],
        ];
    }

    /**
     * @dataProvider ratingColorProvider
     */
    public function testGetRatingColor(string $rating, string $expectedColor): void
    {
        $result = new MetricResult('Test', 100, '', [], $rating);

        $this->assertSame($expectedColor, $result->getRatingColor());
    }

    public static function ratingColorProvider(): array
    {
        return [
            ['A', '#22c55e'],
            ['B', '#84cc16'],
            ['C', '#eab308'],
            ['D', '#f97316'],
            ['F', '#ef4444'],
            ['X', '#94a3b8'], // Unknown rating
        ];
    }

    public function testNullRatingReturnsGrayColor(): void
    {
        $result = new MetricResult('Test', 100);

        $this->assertSame('#94a3b8', $result->getRatingColor());
    }

    public function testMixedValueTypes(): void
    {
        $intResult = new MetricResult('Int', 42);
        $this->assertSame(42, $intResult->value);

        $floatResult = new MetricResult('Float', 3.14);
        $this->assertSame(3.14, $floatResult->value);

        $stringResult = new MetricResult('String', 'high');
        $this->assertSame('high', $stringResult->value);

        $arrayResult = new MetricResult('Array', ['a', 'b']);
        $this->assertSame(['a', 'b'], $arrayResult->value);
    }
}
