<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\Result;

use PhpQuality\Analyzer\Result\MethodResult;
use PHPUnit\Framework\TestCase;

class MethodResultTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $result = new MethodResult(
            name: 'testMethod',
            className: 'TestClass',
            startLine: 10,
            endLine: 20,
            ccn: 5,
            ccnRating: 'B',
            mi: 75.5,
            miRating: 'B',
            loc: 11,
            halstead: ['volume' => 100, 'difficulty' => 5]
        );

        $this->assertSame('testMethod', $result->name);
        $this->assertSame('TestClass', $result->className);
        $this->assertSame(10, $result->startLine);
        $this->assertSame(20, $result->endLine);
        $this->assertSame(5, $result->ccn);
        $this->assertSame('B', $result->ccnRating);
        $this->assertSame(75.5, $result->mi);
        $this->assertSame('B', $result->miRating);
        $this->assertSame(11, $result->loc);
        $this->assertSame(['volume' => 100, 'difficulty' => 5], $result->halstead);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $result = new MethodResult(
            name: 'calculate',
            className: 'Calculator',
            startLine: 5,
            endLine: 15,
            ccn: 3,
            ccnRating: 'A',
            mi: 85.0,
            miRating: 'A',
            loc: 11
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('class', $array);
        $this->assertArrayHasKey('startLine', $array);
        $this->assertArrayHasKey('endLine', $array);
        $this->assertArrayHasKey('ccn', $array);
        $this->assertArrayHasKey('ccnRating', $array);
        $this->assertArrayHasKey('mi', $array);
        $this->assertArrayHasKey('miRating', $array);
        $this->assertArrayHasKey('loc', $array);
        $this->assertArrayHasKey('halstead', $array);

        $this->assertSame('calculate', $array['name']);
        $this->assertSame('Calculator', $array['class']);
    }

    public function testDefaultHalsteadIsEmptyArray(): void
    {
        $result = new MethodResult(
            name: 'test',
            className: 'Test',
            startLine: 1,
            endLine: 5,
            ccn: 1,
            ccnRating: 'A',
            mi: 100.0,
            miRating: 'A',
            loc: 5
        );

        $this->assertSame([], $result->halstead);
    }

    /**
     * @dataProvider ccnRatingProvider
     */
    public function testCcnRatings(int $ccn, string $expectedRating): void
    {
        $result = new MethodResult(
            name: 'test',
            className: 'Test',
            startLine: 1,
            endLine: 10,
            ccn: $ccn,
            ccnRating: $expectedRating,
            mi: 80.0,
            miRating: 'B',
            loc: 10
        );

        $this->assertSame($expectedRating, $result->ccnRating);
    }

    public static function ccnRatingProvider(): array
    {
        return [
            'low complexity' => [1, 'A'],
            'moderate complexity' => [5, 'B'],
            'high complexity' => [8, 'B'],
            'very high complexity' => [15, 'D'],
            'extreme complexity' => [25, 'F'],
        ];
    }
}
