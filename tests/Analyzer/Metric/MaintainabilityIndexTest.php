<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\Metric;

use PhpQuality\Analyzer\Metric\MaintainabilityIndex;
use PhpQuality\Analyzer\Metric\MetricResult;
use PHPUnit\Framework\TestCase;

class MaintainabilityIndexTest extends TestCase
{
    private MaintainabilityIndex $mi;

    protected function setUp(): void
    {
        $this->mi = new MaintainabilityIndex();
    }

    public function testGetName(): void
    {
        $this->assertSame('Maintainability Index', $this->mi->getName());
    }

    public function testGetDescription(): void
    {
        $description = $this->mi->getDescription();

        $this->assertStringContainsString('maintainable', $description);
        $this->assertStringContainsString('0-100', $description);
    }

    public function testCalculateReturnsMetricResult(): void
    {
        $data = [
            'halstead' => ['volume' => 500],
            'ccn' => ['summary' => ['averageCcn' => 5]],
            'loc' => ['lloc' => 100],
        ];

        $result = $this->mi->calculate($data);

        $this->assertInstanceOf(MetricResult::class, $result);
        $this->assertSame('Maintainability Index', $result->name);
        $this->assertIsFloat($result->value);
        $this->assertGreaterThanOrEqual(0, $result->value);
        $this->assertLessThanOrEqual(100, $result->value);
    }

    public function testCalculateWithHighQualityCode(): void
    {
        $data = [
            'halstead' => ['volume' => 50],
            'ccn' => ['summary' => ['averageCcn' => 1]],
            'loc' => ['lloc' => 10],
        ];

        $result = $this->mi->calculate($data);

        $this->assertSame('A', $result->rating);
        $this->assertGreaterThanOrEqual(85, $result->value);
    }

    public function testCalculateWithLowQualityCode(): void
    {
        $data = [
            'halstead' => ['volume' => 50000],
            'ccn' => ['summary' => ['averageCcn' => 50]],
            'loc' => ['lloc' => 5000],
        ];

        $result = $this->mi->calculate($data);

        $this->assertContains($result->rating, ['D', 'F']);
        $this->assertLessThan(40, $result->value);
    }

    public function testCalculateWithEmptyData(): void
    {
        $result = $this->mi->calculate([]);

        $this->assertInstanceOf(MetricResult::class, $result);
        $this->assertGreaterThanOrEqual(0, $result->value);
    }

    public function testCalculateWithZeroValues(): void
    {
        $data = [
            'halstead' => ['volume' => 0],
            'ccn' => ['summary' => ['averageCcn' => 0]],
            'loc' => ['lloc' => 0],
        ];

        $result = $this->mi->calculate($data);

        // Should not produce NaN or errors
        $this->assertIsFloat($result->value);
        $this->assertFalse(is_nan($result->value));
    }

    public function testCalculateDetailsContainsExpectedKeys(): void
    {
        $data = [
            'halstead' => ['volume' => 100],
            'ccn' => ['summary' => ['averageCcn' => 3]],
            'loc' => ['lloc' => 50, 'commentRatio' => 15],
        ];

        $result = $this->mi->calculate($data);
        $details = $result->details;

        $this->assertArrayHasKey('raw', $details);
        $this->assertArrayHasKey('withComments', $details);
        $this->assertArrayHasKey('halsteadVolume', $details);
        $this->assertArrayHasKey('averageCcn', $details);
        $this->assertArrayHasKey('lloc', $details);
        $this->assertArrayHasKey('commentRatio', $details);
    }

    public function testCalculateWithCommentsBonus(): void
    {
        $dataWithoutComments = [
            'halstead' => ['volume' => 100],
            'ccn' => ['summary' => ['averageCcn' => 3]],
            'loc' => ['lloc' => 50, 'commentRatio' => 0],
        ];

        $dataWithComments = [
            'halstead' => ['volume' => 100],
            'ccn' => ['summary' => ['averageCcn' => 3]],
            'loc' => ['lloc' => 50, 'commentRatio' => 20],
        ];

        $resultWithout = $this->mi->calculate($dataWithoutComments);
        $resultWith = $this->mi->calculate($dataWithComments);

        // With comments should have higher 'withComments' value
        $this->assertGreaterThan(
            $resultWithout->details['withComments'],
            $resultWith->details['withComments']
        );
    }

    public function testCalculateForUnit(): void
    {
        $mi = $this->mi->calculateForUnit(100.0, 5.0, 50);

        $this->assertIsFloat($mi);
        $this->assertGreaterThanOrEqual(0, $mi);
        $this->assertLessThanOrEqual(100, $mi);
    }

    public function testCalculateForUnitWithZeroValues(): void
    {
        $mi = $this->mi->calculateForUnit(0.0, 0.0, 0);

        $this->assertIsFloat($mi);
        $this->assertFalse(is_nan($mi));
    }

    /**
     * @dataProvider ratingProvider
     */
    public function testRatingThresholds(float $volume, float $ccn, int $lloc, string $expectedRating): void
    {
        $data = [
            'halstead' => ['volume' => $volume],
            'ccn' => ['summary' => ['averageCcn' => $ccn]],
            'loc' => ['lloc' => $lloc],
        ];

        $result = $this->mi->calculate($data);

        $this->assertSame($expectedRating, $result->rating);
    }

    public static function ratingProvider(): array
    {
        return [
            'excellent' => [50, 1, 10, 'A'],
            'good' => [200, 3, 50, 'B'],
            'moderate' => [1000, 10, 200, 'C'],
            'poor' => [5000, 20, 500, 'D'],
        ];
    }

    public function testValueIsNormalizedTo100(): void
    {
        // Even with extreme values, should cap at 100
        $data = [
            'halstead' => ['volume' => 1],
            'ccn' => ['summary' => ['averageCcn' => 0]],
            'loc' => ['lloc' => 1],
        ];

        $result = $this->mi->calculate($data);

        $this->assertLessThanOrEqual(100, $result->value);
    }

    public function testValueIsNormalizedToZero(): void
    {
        // Extreme poor values should floor at 0
        $data = [
            'halstead' => ['volume' => 1000000],
            'ccn' => ['summary' => ['averageCcn' => 100]],
            'loc' => ['lloc' => 100000],
        ];

        $result = $this->mi->calculate($data);

        $this->assertGreaterThanOrEqual(0, $result->value);
    }
}
