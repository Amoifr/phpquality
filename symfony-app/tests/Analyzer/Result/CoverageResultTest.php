<?php

declare(strict_types=1);

namespace App\Tests\Analyzer\Result;

use App\Analyzer\Result\CoverageResult;
use PHPUnit\Framework\TestCase;

class CoverageResultTest extends TestCase
{
    public function testDefaultConstructorValues(): void
    {
        $result = new CoverageResult();

        $this->assertFalse($result->found);
        $this->assertSame(0.0, $result->lineCoverage);
        $this->assertSame(0.0, $result->methodCoverage);
        $this->assertSame(0.0, $result->classCoverage);
        $this->assertSame(0, $result->coveredLines);
        $this->assertSame(0, $result->totalLines);
        $this->assertSame(0, $result->coveredMethods);
        $this->assertSame(0, $result->totalMethods);
        $this->assertSame(0, $result->coveredClasses);
        $this->assertSame(0, $result->totalClasses);
        $this->assertSame([], $result->files);
        $this->assertSame([], $result->packages);
        $this->assertSame('F', $result->rating);
        $this->assertNull($result->generatedAt);
    }

    public function testConstructorWithAllValues(): void
    {
        $files = [
            ['path' => '/path/to/file1.php', 'coverage' => 80.0],
            ['path' => '/path/to/file2.php', 'coverage' => 60.0],
        ];

        $packages = [
            ['name' => 'App\\Domain', 'coverage' => 75.0],
        ];

        $result = new CoverageResult(
            found: true,
            lineCoverage: 72.5,
            methodCoverage: 68.3,
            classCoverage: 80.0,
            coveredLines: 725,
            totalLines: 1000,
            coveredMethods: 68,
            totalMethods: 100,
            coveredClasses: 16,
            totalClasses: 20,
            files: $files,
            packages: $packages,
            rating: 'B',
            generatedAt: '2024-01-15 10:30:00'
        );

        $this->assertTrue($result->found);
        $this->assertSame(72.5, $result->lineCoverage);
        $this->assertSame(68.3, $result->methodCoverage);
        $this->assertSame(80.0, $result->classCoverage);
        $this->assertSame(725, $result->coveredLines);
        $this->assertSame(1000, $result->totalLines);
        $this->assertSame(68, $result->coveredMethods);
        $this->assertSame(100, $result->totalMethods);
        $this->assertSame(16, $result->coveredClasses);
        $this->assertSame(20, $result->totalClasses);
        $this->assertCount(2, $result->files);
        $this->assertCount(1, $result->packages);
        $this->assertSame('B', $result->rating);
        $this->assertSame('2024-01-15 10:30:00', $result->generatedAt);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $result = new CoverageResult(
            found: true,
            lineCoverage: 85.0,
            methodCoverage: 80.0,
            classCoverage: 90.0,
            coveredLines: 850,
            totalLines: 1000,
            coveredMethods: 80,
            totalMethods: 100,
            coveredClasses: 18,
            totalClasses: 20,
            files: [],
            packages: [],
            rating: 'A',
            generatedAt: '2024-01-15 12:00:00'
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('found', $array);
        $this->assertArrayHasKey('lineCoverage', $array);
        $this->assertArrayHasKey('methodCoverage', $array);
        $this->assertArrayHasKey('classCoverage', $array);
        $this->assertArrayHasKey('coveredLines', $array);
        $this->assertArrayHasKey('totalLines', $array);
        $this->assertArrayHasKey('coveredMethods', $array);
        $this->assertArrayHasKey('totalMethods', $array);
        $this->assertArrayHasKey('coveredClasses', $array);
        $this->assertArrayHasKey('totalClasses', $array);
        $this->assertArrayHasKey('rating', $array);
        $this->assertArrayHasKey('generatedAt', $array);
        $this->assertArrayHasKey('files', $array);
        $this->assertArrayHasKey('packages', $array);

        $this->assertTrue($array['found']);
        $this->assertSame(85.0, $array['lineCoverage']);
        $this->assertSame('A', $array['rating']);
    }

    public function testNotFoundResult(): void
    {
        $result = new CoverageResult(found: false);

        $this->assertFalse($result->found);
        $this->assertSame('F', $result->rating);

        $array = $result->toArray();
        $this->assertFalse($array['found']);
    }

    /**
     * @dataProvider coverageRatingProvider
     */
    public function testCoverageRatings(float $coverage, string $expectedRating): void
    {
        $result = new CoverageResult(
            found: true,
            lineCoverage: $coverage,
            rating: $expectedRating
        );

        $this->assertSame($expectedRating, $result->rating);
    }

    public static function coverageRatingProvider(): array
    {
        return [
            'excellent coverage 100%' => [100.0, 'A'],
            'excellent coverage 80%' => [80.0, 'A'],
            'good coverage 79%' => [79.0, 'B'],
            'good coverage 60%' => [60.0, 'B'],
            'moderate coverage 59%' => [59.0, 'C'],
            'moderate coverage 40%' => [40.0, 'C'],
            'low coverage 39%' => [39.0, 'D'],
            'low coverage 20%' => [20.0, 'D'],
            'critical coverage 19%' => [19.0, 'F'],
            'critical coverage 0%' => [0.0, 'F'],
        ];
    }
}
