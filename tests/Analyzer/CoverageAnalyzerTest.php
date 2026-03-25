<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer;

use PhpQuality\Analyzer\CoverageAnalyzer;
use PhpQuality\Analyzer\Result\CoverageResult;
use PHPUnit\Framework\TestCase;

class CoverageAnalyzerTest extends TestCase
{
    private CoverageAnalyzer $analyzer;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->analyzer = new CoverageAnalyzer();
        $this->fixturesPath = __DIR__ . '/Fixtures';

        if (!is_dir($this->fixturesPath)) {
            mkdir($this->fixturesPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        $files = glob($this->fixturesPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->fixturesPath)) {
            rmdir($this->fixturesPath);
        }
    }

    public function testAnalyzeNonExistentFile(): void
    {
        $result = $this->analyzer->analyze('/nonexistent/path/coverage.xml');

        $this->assertInstanceOf(CoverageResult::class, $result);
        $this->assertFalse($result->found);
    }

    public function testAnalyzeInvalidXmlFile(): void
    {
        $invalidXml = $this->fixturesPath . '/invalid.xml';
        file_put_contents($invalidXml, 'not valid xml content');

        $result = $this->analyzer->analyze($invalidXml);

        $this->assertFalse($result->found);
    }

    public function testAnalyzeCloverFormat(): void
    {
        $cloverXml = $this->createCloverXmlFile();

        $result = $this->analyzer->analyze($cloverXml);

        $this->assertTrue($result->found);
        $this->assertSame(75.0, $result->lineCoverage);
        $this->assertSame(66.67, $result->methodCoverage);
        $this->assertSame(50.0, $result->classCoverage);
        $this->assertSame(75, $result->coveredLines);
        $this->assertSame(100, $result->totalLines);
        $this->assertSame(2, $result->coveredMethods);
        $this->assertSame(3, $result->totalMethods);
        $this->assertSame(1, $result->coveredClasses);
        $this->assertSame(2, $result->totalClasses);
    }

    public function testAnalyzeCloverFormatRatingA(): void
    {
        $cloverXml = $this->createCloverXmlFileWithCoverage(85, 100);

        $result = $this->analyzer->analyze($cloverXml);

        $this->assertTrue($result->found);
        $this->assertSame('A', $result->rating);
    }

    public function testAnalyzeCloverFormatRatingB(): void
    {
        $cloverXml = $this->createCloverXmlFileWithCoverage(65, 100);

        $result = $this->analyzer->analyze($cloverXml);

        $this->assertTrue($result->found);
        $this->assertSame('B', $result->rating);
    }

    public function testAnalyzeCloverFormatRatingC(): void
    {
        $cloverXml = $this->createCloverXmlFileWithCoverage(45, 100);

        $result = $this->analyzer->analyze($cloverXml);

        $this->assertTrue($result->found);
        $this->assertSame('C', $result->rating);
    }

    public function testAnalyzeCloverFormatRatingD(): void
    {
        $cloverXml = $this->createCloverXmlFileWithCoverage(25, 100);

        $result = $this->analyzer->analyze($cloverXml);

        $this->assertTrue($result->found);
        $this->assertSame('D', $result->rating);
    }

    public function testAnalyzeCloverFormatRatingF(): void
    {
        $cloverXml = $this->createCloverXmlFileWithCoverage(10, 100);

        $result = $this->analyzer->analyze($cloverXml);

        $this->assertTrue($result->found);
        $this->assertSame('F', $result->rating);
    }

    public function testAnalyzeEmptyProject(): void
    {
        $emptyClover = $this->fixturesPath . '/empty_clover.xml';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
  </project>
</coverage>
XML;
        file_put_contents($emptyClover, $xml);

        $result = $this->analyzer->analyze($emptyClover);

        $this->assertFalse($result->found);
    }

    public function testAnalyzeUnknownFormat(): void
    {
        $unknownXml = $this->fixturesPath . '/unknown.xml';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<unknown>
  <data>something</data>
</unknown>
XML;
        file_put_contents($unknownXml, $xml);

        $result = $this->analyzer->analyze($unknownXml);

        $this->assertFalse($result->found);
    }

    public function testCoverageResultToArray(): void
    {
        $cloverXml = $this->createCloverXmlFile();

        $result = $this->analyzer->analyze($cloverXml);
        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('found', $array);
        $this->assertArrayHasKey('lineCoverage', $array);
        $this->assertArrayHasKey('rating', $array);
        $this->assertTrue($array['found']);
    }

    private function createCloverXmlFile(): string
    {
        $path = $this->fixturesPath . '/clover.xml';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <metrics files="2" loc="100" ncloc="90" classes="2" methods="3"
             coveredmethods="2" conditionals="0" coveredconditionals="0"
             statements="100" coveredstatements="75" elements="103" coveredelements="77"
             coveredclasses="1"/>
    <file name="/path/to/File1.php">
      <class name="File1" namespace="App">
        <metrics complexity="5" methods="2" coveredmethods="2" statements="50" coveredstatements="45"/>
      </class>
      <metrics loc="50" ncloc="45" classes="1" methods="2" coveredmethods="2" statements="50" coveredstatements="45"/>
    </file>
    <file name="/path/to/File2.php">
      <class name="File2" namespace="App">
        <metrics complexity="3" methods="1" coveredmethods="0" statements="50" coveredstatements="30"/>
      </class>
      <metrics loc="50" ncloc="45" classes="1" methods="1" coveredmethods="0" statements="50" coveredstatements="30"/>
    </file>
  </project>
</coverage>
XML;
        file_put_contents($path, $xml);
        return $path;
    }

    public function testAnalyzeFromProjectFindsExistingCoverage(): void
    {
        $xml = $this->createCloverXmlFileWithCoverage(80, 100);
        rename($xml, $this->fixturesPath . '/coverage.xml');

        $result = $this->analyzer->analyzeFromProject($this->fixturesPath, false);

        $this->assertTrue($result->found);
    }

    public function testAnalyzeFromProjectReturnsNotFoundWhenNoCoverage(): void
    {
        $result = $this->analyzer->analyzeFromProject($this->fixturesPath, false);

        $this->assertFalse($result->found);
    }

    public function testExtractsFileCoverage(): void
    {
        $cloverXml = $this->createCloverXmlFile();

        $result = $this->analyzer->analyze($cloverXml);

        $this->assertNotEmpty($result->files);
        $this->assertCount(2, $result->files);
    }

    public function testExtractsPackageCoverage(): void
    {
        $path = $this->fixturesPath . '/packages.xml';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <metrics statements="100" coveredstatements="80" methods="10" coveredmethods="8" classes="2" coveredclasses="2"/>
    <package name="App\Service">
      <metrics statements="50" coveredstatements="40" files="2" classes="1"/>
      <file name="/path/to/UserService.php">
        <metrics statements="50" coveredstatements="40" methods="5" coveredmethods="4"/>
      </file>
    </package>
  </project>
</coverage>
XML;
        file_put_contents($path, $xml);

        $result = $this->analyzer->analyze($path);

        $this->assertNotEmpty($result->packages);
        $this->assertSame('App\Service', $result->packages[0]['name']);
    }

    public function testExtractsClassDetailsFromFile(): void
    {
        $cloverXml = $this->createCloverXmlFile();

        $result = $this->analyzer->analyze($cloverXml);

        $this->assertNotEmpty($result->files[0]['classes']);
    }

    public function testExtractsUncoveredLines(): void
    {
        $path = $this->fixturesPath . '/uncovered.xml';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <metrics statements="100" coveredstatements="80" methods="10" coveredmethods="8" classes="1" coveredclasses="1"/>
    <file name="/path/to/File.php">
      <metrics statements="10" coveredstatements="8" methods="2" coveredmethods="2"/>
      <line num="5" type="stmt" count="0"/>
      <line num="10" type="stmt" count="0"/>
      <line num="15" type="stmt" count="5"/>
    </file>
  </project>
</coverage>
XML;
        file_put_contents($path, $xml);

        $result = $this->analyzer->analyze($path);

        $this->assertContains(5, $result->files[0]['uncoveredLines']);
        $this->assertContains(10, $result->files[0]['uncoveredLines']);
    }

    public function testFilesSortedByCoverageAscending(): void
    {
        $path = $this->fixturesPath . '/sorted.xml';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <metrics statements="150" coveredstatements="100" methods="15" coveredmethods="10" classes="3" coveredclasses="2"/>
    <file name="/path/to/High.php">
      <metrics statements="50" coveredstatements="45" methods="5" coveredmethods="5"/>
    </file>
    <file name="/path/to/Low.php">
      <metrics statements="50" coveredstatements="10" methods="5" coveredmethods="1"/>
    </file>
    <file name="/path/to/Medium.php">
      <metrics statements="50" coveredstatements="25" methods="5" coveredmethods="3"/>
    </file>
  </project>
</coverage>
XML;
        file_put_contents($path, $xml);

        $result = $this->analyzer->analyze($path);

        $this->assertSame('Low.php', $result->files[0]['name']);
        $this->assertSame('Medium.php', $result->files[1]['name']);
        $this->assertSame('High.php', $result->files[2]['name']);
    }

    public function testSkipsFilesWithZeroStatements(): void
    {
        $path = $this->fixturesPath . '/empty_file.xml';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <metrics statements="50" coveredstatements="40" methods="5" coveredmethods="4" classes="1" coveredclasses="1"/>
    <file name="/path/to/Empty.php">
      <metrics statements="0" coveredstatements="0" methods="0" coveredmethods="0"/>
    </file>
    <file name="/path/to/Real.php">
      <metrics statements="50" coveredstatements="40" methods="5" coveredmethods="4"/>
    </file>
  </project>
</coverage>
XML;
        file_put_contents($path, $xml);

        $result = $this->analyzer->analyze($path);

        $this->assertCount(1, $result->files);
        $this->assertSame('Real.php', $result->files[0]['name']);
    }

    public function testExtractsGeneratedAt(): void
    {
        $cloverXml = $this->createCloverXmlFile();

        $result = $this->analyzer->analyze($cloverXml);

        $this->assertNotNull($result->generatedAt);
    }

    public function testZeroCoverageCalculation(): void
    {
        $path = $this->fixturesPath . '/zero.xml';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <metrics statements="0" coveredstatements="0" methods="0" coveredmethods="0" classes="0" coveredclasses="0"/>
  </project>
</coverage>
XML;
        file_put_contents($path, $xml);

        $result = $this->analyzer->analyze($path);

        $this->assertTrue($result->found);
        $this->assertSame(0.0, $result->lineCoverage);
        $this->assertSame(0.0, $result->methodCoverage);
        $this->assertSame(0.0, $result->classCoverage);
    }

    public function testCalculatesFullyCoveredClasses(): void
    {
        $path = $this->fixturesPath . '/full_classes.xml';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <metrics files="3" loc="300" ncloc="300" classes="3" methods="10"
             coveredmethods="7" conditionals="0" coveredconditionals="0"
             statements="300" coveredstatements="250" elements="310" coveredelements="257"/>
    <file name="/path/to/FullyCovered.php">
      <class name="FullyCovered" namespace="App">
        <metrics complexity="5" methods="3" coveredmethods="3" statements="100" coveredstatements="100"/>
      </class>
      <metrics loc="100" ncloc="100" classes="1" methods="3" coveredmethods="3" statements="100" coveredstatements="100"/>
    </file>
    <file name="/path/to/PartiallyCovered.php">
      <class name="PartiallyCovered" namespace="App">
        <metrics complexity="3" methods="4" coveredmethods="2" statements="100" coveredstatements="80"/>
      </class>
      <metrics loc="100" ncloc="100" classes="1" methods="4" coveredmethods="2" statements="100" coveredstatements="80"/>
    </file>
    <file name="/path/to/AlsoFullyCovered.php">
      <class name="AlsoFullyCovered" namespace="App">
        <metrics complexity="2" methods="3" coveredmethods="3" statements="100" coveredstatements="70"/>
      </class>
      <metrics loc="100" ncloc="100" classes="1" methods="3" coveredmethods="3" statements="100" coveredstatements="70"/>
    </file>
  </project>
</coverage>
XML;
        file_put_contents($path, $xml);

        $result = $this->analyzer->analyze($path);

        $this->assertTrue($result->found);
        // 2 out of 3 classes have all methods covered
        $this->assertSame(2, $result->coveredClasses);
        $this->assertSame(3, $result->totalClasses);
        // Class coverage should be ~66.67%
        $this->assertEqualsWithDelta(66.67, $result->classCoverage, 0.01);
    }

    public function testCalculatesFullyCoveredClassesInPackages(): void
    {
        $path = $this->fixturesPath . '/packages_full.xml';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <metrics files="2" loc="200" ncloc="200" classes="2" methods="6"
             coveredmethods="6" conditionals="0" coveredconditionals="0"
             statements="200" coveredstatements="200" elements="206" coveredelements="206"/>
    <package name="App\\Service">
      <metrics statements="200" coveredstatements="200" files="2" classes="2"/>
      <file name="/path/to/Service1.php">
        <class name="Service1" namespace="App\\Service">
          <metrics complexity="2" methods="3" coveredmethods="3" statements="100" coveredstatements="100"/>
        </class>
        <metrics loc="100" ncloc="100" classes="1" methods="3" coveredmethods="3" statements="100" coveredstatements="100"/>
      </file>
      <file name="/path/to/Service2.php">
        <class name="Service2" namespace="App\\Service">
          <metrics complexity="2" methods="3" coveredmethods="3" statements="100" coveredstatements="100"/>
        </class>
        <metrics loc="100" ncloc="100" classes="1" methods="3" coveredmethods="3" statements="100" coveredstatements="100"/>
      </file>
    </package>
  </project>
</coverage>
XML;
        file_put_contents($path, $xml);

        $result = $this->analyzer->analyze($path);

        $this->assertTrue($result->found);
        // Both classes have all methods covered
        $this->assertSame(2, $result->coveredClasses);
        $this->assertSame(100.0, $result->classCoverage);
    }

    private function createCloverXmlFileWithCoverage(int $covered, int $total): string
    {
        $path = $this->fixturesPath . '/clover_' . $covered . '.xml';
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <metrics files="1" loc="$total" ncloc="$total" classes="1" methods="1"
             coveredmethods="1" conditionals="0" coveredconditionals="0"
             statements="$total" coveredstatements="$covered" elements="$total" coveredelements="$covered"
             coveredclasses="1"/>
  </project>
</coverage>
XML;
        file_put_contents($path, $xml);
        return $path;
    }
}
