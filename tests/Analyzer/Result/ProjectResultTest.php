<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\Result;

use PhpQuality\Analyzer\Result\ProjectResult;
use PhpQuality\Analyzer\Result\FileResult;
use PhpQuality\Analyzer\Result\ClassResult;
use PhpQuality\Analyzer\Result\MethodResult;
use PhpQuality\Analyzer\ProjectType\ProjectTypeInterface;
use PHPUnit\Framework\TestCase;

class ProjectResultTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('symfony');

        $result = new ProjectResult(
            sourcePath: '/path/to/project',
            projectType: $projectType,
            files: [],
            summary: ['totalLoc' => 1000],
            analyzedAt: new \DateTimeImmutable('2024-01-15 10:00:00')
        );

        $this->assertSame('/path/to/project', $result->sourcePath);
        $this->assertSame($projectType, $result->projectType);
        $this->assertEmpty($result->files);
        $this->assertSame(1000, $result->summary['totalLoc']);
    }

    public function testGetFileCount(): void
    {
        $files = [
            $this->createFileResult('File1.php'),
            $this->createFileResult('File2.php'),
            $this->createFileResult('File3.php'),
        ];

        $result = $this->createProjectResult($files);

        $this->assertSame(3, $result->getFileCount());
    }

    public function testGetClassCount(): void
    {
        $file1 = $this->createFileResultWithClasses(2);
        $file2 = $this->createFileResultWithClasses(3);

        $result = $this->createProjectResult([$file1, $file2]);

        $this->assertSame(5, $result->getClassCount());
    }

    public function testGetMethodCount(): void
    {
        $method1 = new MethodResult('m1', 'Class1', 1, 10, 1, 'A', 90.0, 'A', 10);
        $method2 = new MethodResult('m2', 'Class1', 11, 20, 2, 'A', 85.0, 'A', 10);
        $method3 = new MethodResult('m3', 'Class2', 1, 15, 3, 'A', 80.0, 'B', 15);

        $class1 = new ClassResult(
            'Class1', 'App', '/path/file1.php', 1, 50, [$method1, $method2],
            0.2, 'A', 50, 2, 1, 2, 1.5, 87.0, 'A'
        );
        $class2 = new ClassResult(
            'Class2', 'App', '/path/file2.php', 1, 30, [$method3],
            0.1, 'A', 30, 1, 0, 3, 3.0, 80.0, 'B'
        );

        $file1 = new FileResult(
            '/path/file1.php', 'file1.php', [$class1],
            ['loc' => 50], ['summary' => ['maxCcn' => 2]], [], [],
            87.0, 'A'
        );
        $file2 = new FileResult(
            '/path/file2.php', 'file2.php', [$class2],
            ['loc' => 30], ['summary' => ['maxCcn' => 3]], [], [],
            80.0, 'B'
        );

        $result = $this->createProjectResult([$file1, $file2]);

        $this->assertSame(3, $result->getMethodCount());
    }

    public function testGetTotalLoc(): void
    {
        $result = $this->createProjectResult([], ['totalLoc' => 5000]);

        $this->assertSame(5000, $result->getTotalLoc());
    }

    public function testGetAverageMi(): void
    {
        $result = $this->createProjectResult([], ['averageMi' => 75.5]);

        $this->assertSame(75.5, $result->getAverageMi());
    }

    public function testGetAverageCcn(): void
    {
        $result = $this->createProjectResult([], ['averageCcn' => 4.2]);

        $this->assertSame(4.2, $result->getAverageCcn());
    }

    public function testGetAverageLcom(): void
    {
        $result = $this->createProjectResult([], ['averageLcom' => 0.35]);

        $this->assertSame(0.35, $result->getAverageLcom());
    }

    public function testGetWorstFilesByCcn(): void
    {
        $file1 = $this->createFileResultWithCcn('file1.php', 5);
        $file2 = $this->createFileResultWithCcn('file2.php', 15);
        $file3 = $this->createFileResultWithCcn('file3.php', 10);

        $result = $this->createProjectResult([$file1, $file2, $file3]);

        $worst = $result->getWorstFiles('ccn', 2);

        $this->assertCount(2, $worst);
        $this->assertSame('file2.php', $worst[0]->relativePath);
        $this->assertSame('file3.php', $worst[1]->relativePath);
    }

    public function testGetWorstFilesByMi(): void
    {
        $file1 = $this->createFileResultWithMi('file1.php', 90.0);
        $file2 = $this->createFileResultWithMi('file2.php', 30.0);
        $file3 = $this->createFileResultWithMi('file3.php', 60.0);

        $result = $this->createProjectResult([$file1, $file2, $file3]);

        $worst = $result->getWorstFiles('mi', 2);

        $this->assertCount(2, $worst);
        $this->assertSame('file2.php', $worst[0]->relativePath);
        $this->assertSame('file3.php', $worst[1]->relativePath);
    }

    public function testGetAllClasses(): void
    {
        $class1 = new ClassResult('Class1', 'App', '/f1.php', 1, 50, [], 0.1, 'A', 50, 0, 0, 0, 0.0, 90.0, 'A');
        $class2 = new ClassResult('Class2', 'App', '/f2.php', 1, 30, [], 0.2, 'A', 30, 0, 0, 0, 0.0, 85.0, 'A');

        $file1 = new FileResult('/f1.php', 'f1.php', [$class1], ['loc' => 50], [], [], [], 90.0, 'A');
        $file2 = new FileResult('/f2.php', 'f2.php', [$class2], ['loc' => 30], [], [], [], 85.0, 'A');

        $result = $this->createProjectResult([$file1, $file2]);

        $classes = $result->getAllClasses();

        $this->assertCount(2, $classes);
        $this->assertSame('Class1', $classes[0]->name);
        $this->assertSame('Class2', $classes[1]->name);
    }

    public function testGetClassesByCategory(): void
    {
        $class1 = new ClassResult('UserController', 'App', '/f1.php', 1, 50, [], 0.1, 'A', 50, 0, 0, 0, 0.0, 90.0, 'A', 'Controller');
        $class2 = new ClassResult('UserService', 'App', '/f2.php', 1, 30, [], 0.2, 'A', 30, 0, 0, 0, 0.0, 85.0, 'A', 'Service');
        $class3 = new ClassResult('OrderController', 'App', '/f3.php', 1, 40, [], 0.15, 'A', 40, 0, 0, 0, 0.0, 88.0, 'A', 'Controller');
        $class4 = new ClassResult('Helper', 'App', '/f4.php', 1, 20, [], 0.1, 'A', 20, 0, 0, 0, 0.0, 95.0, 'A');

        $file1 = new FileResult('/f1.php', 'f1.php', [$class1], ['loc' => 50], [], [], [], 90.0, 'A');
        $file2 = new FileResult('/f2.php', 'f2.php', [$class2], ['loc' => 30], [], [], [], 85.0, 'A');
        $file3 = new FileResult('/f3.php', 'f3.php', [$class3], ['loc' => 40], [], [], [], 88.0, 'A');
        $file4 = new FileResult('/f4.php', 'f4.php', [$class4], ['loc' => 20], [], [], [], 95.0, 'A');

        $result = $this->createProjectResult([$file1, $file2, $file3, $file4]);

        $grouped = $result->getClassesByCategory();

        $this->assertArrayHasKey('Controller', $grouped);
        $this->assertArrayHasKey('Service', $grouped);
        $this->assertArrayHasKey('Other', $grouped);
        $this->assertCount(2, $grouped['Controller']);
        $this->assertCount(1, $grouped['Service']);
        $this->assertCount(1, $grouped['Other']);
    }

    public function testToArray(): void
    {
        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('php');

        $result = new ProjectResult(
            sourcePath: '/path/to/project',
            projectType: $projectType,
            files: [],
            summary: ['totalLoc' => 100],
            analyzedAt: new \DateTimeImmutable('2024-01-15 10:00:00')
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('sourcePath', $array);
        $this->assertArrayHasKey('projectType', $array);
        $this->assertArrayHasKey('analyzedAt', $array);
        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayHasKey('files', $array);
        $this->assertSame('php', $array['projectType']);
        $this->assertSame('2024-01-15 10:00:00', $array['analyzedAt']);
    }

    private function createProjectResult(array $files, array $summary = []): ProjectResult
    {
        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('php');

        return new ProjectResult(
            sourcePath: '/path/to/project',
            projectType: $projectType,
            files: $files,
            summary: $summary,
            analyzedAt: new \DateTimeImmutable()
        );
    }

    private function createFileResult(string $name): FileResult
    {
        return new FileResult(
            path: '/path/to/' . $name,
            relativePath: $name,
            classes: [],
            loc: ['loc' => 100],
            ccn: ['summary' => ['maxCcn' => 5]],
            halstead: [],
            lcom: [],
            mi: 80.0,
            miRating: 'B'
        );
    }

    private function createFileResultWithClasses(int $classCount): FileResult
    {
        $classes = [];
        for ($i = 0; $i < $classCount; $i++) {
            $classes[] = new ClassResult(
                'Class' . $i, 'App', '/path/file.php', 1, 50, [],
                0.1, 'A', 50, 0, 0, 0, 0.0, 90.0, 'A'
            );
        }

        return new FileResult(
            path: '/path/file.php',
            relativePath: 'file.php',
            classes: $classes,
            loc: ['loc' => 100],
            ccn: ['summary' => ['maxCcn' => 5]],
            halstead: [],
            lcom: [],
            mi: 80.0,
            miRating: 'B'
        );
    }

    private function createFileResultWithCcn(string $name, int $ccn): FileResult
    {
        return new FileResult(
            path: '/path/to/' . $name,
            relativePath: $name,
            classes: [],
            loc: ['loc' => 100],
            ccn: ['summary' => ['maxCcn' => $ccn, 'averageCcn' => $ccn]],
            halstead: [],
            lcom: [],
            mi: 80.0,
            miRating: 'B'
        );
    }

    private function createFileResultWithMi(string $name, float $mi): FileResult
    {
        return new FileResult(
            path: '/path/to/' . $name,
            relativePath: $name,
            classes: [],
            loc: ['loc' => 100],
            ccn: ['summary' => ['maxCcn' => 5]],
            halstead: [],
            lcom: [],
            mi: $mi,
            miRating: $mi >= 85 ? 'A' : ($mi >= 65 ? 'B' : 'C')
        );
    }
}
