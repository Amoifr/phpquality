<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\Architecture;

use PhpQuality\Analyzer\Architecture\LayerDetector;
use PhpQuality\Analyzer\ProjectType\ProjectTypeInterface;
use PHPUnit\Framework\TestCase;

class LayerDetectorTest extends TestCase
{
    private LayerDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new LayerDetector();
    }

    /**
     * @dataProvider controllerLayerProvider
     */
    public function testDetectControllerLayer(string $fqn): void
    {
        $layer = $this->detector->detectLayer($fqn);

        $this->assertSame(LayerDetector::LAYER_CONTROLLER, $layer);
    }

    public static function controllerLayerProvider(): array
    {
        return [
            ['App\\Controller\\UserController'],
            ['App\\Http\\Controller\\ApiController'],
            ['App\\Action\\CreateUserAction'],
            ['Acme\\Web\\Controller\\HomeController'],
        ];
    }

    /**
     * @dataProvider applicationLayerProvider
     */
    public function testDetectApplicationLayer(string $fqn): void
    {
        $layer = $this->detector->detectLayer($fqn);

        $this->assertSame(LayerDetector::LAYER_APPLICATION, $layer);
    }

    public static function applicationLayerProvider(): array
    {
        return [
            ['App\\Service\\UserService'],
            ['App\\Application\\UseCase\\CreateUser'],
            ['App\\Handler\\CreateUserHandler'],
            ['App\\Command\\RegisterUserCommandHandler'],
            ['App\\Query\\GetUserQueryHandler'],
        ];
    }

    /**
     * @dataProvider domainLayerProvider
     */
    public function testDetectDomainLayer(string $fqn): void
    {
        $layer = $this->detector->detectLayer($fqn);

        $this->assertSame(LayerDetector::LAYER_DOMAIN, $layer);
    }

    public static function domainLayerProvider(): array
    {
        return [
            ['App\\Domain\\Entity\\User'],
            ['App\\Entity\\Order'],
            ['App\\Model\\Product'],
            ['App\\ValueObject\\Email'],
            ['App\\Domain\\Specification\\ActiveUserSpecification'],
        ];
    }

    /**
     * @dataProvider infrastructureLayerProvider
     */
    public function testDetectInfrastructureLayer(string $fqn): void
    {
        $layer = $this->detector->detectLayer($fqn);

        $this->assertSame(LayerDetector::LAYER_INFRASTRUCTURE, $layer);
    }

    public static function infrastructureLayerProvider(): array
    {
        return [
            ['App\\Repository\\UserRepository'],
            ['App\\Infrastructure\\Persistence\\DoctrineUserRepository'],
            ['App\\Adapter\\EmailAdapter'],
            ['App\\Gateway\\PaymentGateway'],
            ['App\\Client\\HttpClient'],
        ];
    }

    public function testDetectOtherLayer(): void
    {
        $layer = $this->detector->detectLayer('App\\Util\\StringHelper');

        $this->assertSame(LayerDetector::LAYER_OTHER, $layer);
    }

    public function testDetectLayers(): void
    {
        $classes = [
            'App\\Controller\\UserController',
            'App\\Service\\UserService',
            'App\\Entity\\User',
            'App\\Repository\\UserRepository',
            'App\\Util\\Helper',
        ];

        $assignments = $this->detector->detectLayers($classes);

        $this->assertCount(5, $assignments);
        $this->assertSame(LayerDetector::LAYER_CONTROLLER, $assignments['App\\Controller\\UserController']);
        $this->assertSame(LayerDetector::LAYER_APPLICATION, $assignments['App\\Service\\UserService']);
        $this->assertSame(LayerDetector::LAYER_DOMAIN, $assignments['App\\Entity\\User']);
        $this->assertSame(LayerDetector::LAYER_INFRASTRUCTURE, $assignments['App\\Repository\\UserRepository']);
        $this->assertSame(LayerDetector::LAYER_OTHER, $assignments['App\\Util\\Helper']);
    }

    public function testGetLayerStats(): void
    {
        $assignments = [
            'Class1' => LayerDetector::LAYER_CONTROLLER,
            'Class2' => LayerDetector::LAYER_CONTROLLER,
            'Class3' => LayerDetector::LAYER_APPLICATION,
            'Class4' => LayerDetector::LAYER_DOMAIN,
            'Class5' => LayerDetector::LAYER_DOMAIN,
            'Class6' => LayerDetector::LAYER_DOMAIN,
            'Class7' => LayerDetector::LAYER_INFRASTRUCTURE,
            'Class8' => LayerDetector::LAYER_OTHER,
        ];

        $stats = $this->detector->getLayerStats($assignments);

        $this->assertSame(2, $stats[LayerDetector::LAYER_CONTROLLER]);
        $this->assertSame(1, $stats[LayerDetector::LAYER_APPLICATION]);
        $this->assertSame(3, $stats[LayerDetector::LAYER_DOMAIN]);
        $this->assertSame(1, $stats[LayerDetector::LAYER_INFRASTRUCTURE]);
        $this->assertSame(1, $stats[LayerDetector::LAYER_OTHER]);
    }

    public function testGetLayerStatsWithEmptyInput(): void
    {
        $stats = $this->detector->getLayerStats([]);

        $this->assertSame(0, $stats[LayerDetector::LAYER_CONTROLLER]);
        $this->assertSame(0, $stats[LayerDetector::LAYER_APPLICATION]);
        $this->assertSame(0, $stats[LayerDetector::LAYER_DOMAIN]);
        $this->assertSame(0, $stats[LayerDetector::LAYER_INFRASTRUCTURE]);
        $this->assertSame(0, $stats[LayerDetector::LAYER_OTHER]);
    }

    public function testGetAllowedDependencies(): void
    {
        $controllerAllowed = $this->detector->getAllowedDependencies(LayerDetector::LAYER_CONTROLLER);
        $this->assertContains(LayerDetector::LAYER_APPLICATION, $controllerAllowed);
        $this->assertContains(LayerDetector::LAYER_DOMAIN, $controllerAllowed);
        $this->assertContains(LayerDetector::LAYER_INFRASTRUCTURE, $controllerAllowed);

        $applicationAllowed = $this->detector->getAllowedDependencies(LayerDetector::LAYER_APPLICATION);
        $this->assertContains(LayerDetector::LAYER_DOMAIN, $applicationAllowed);
        $this->assertNotContains(LayerDetector::LAYER_CONTROLLER, $applicationAllowed);

        $domainAllowed = $this->detector->getAllowedDependencies(LayerDetector::LAYER_DOMAIN);
        $this->assertContains(LayerDetector::LAYER_OTHER, $domainAllowed);
        $this->assertNotContains(LayerDetector::LAYER_INFRASTRUCTURE, $domainAllowed);
        $this->assertNotContains(LayerDetector::LAYER_APPLICATION, $domainAllowed);
    }

    /**
     * @dataProvider dependencyAllowedProvider
     */
    public function testIsDependencyAllowed(string $from, string $to, bool $expected): void
    {
        $result = $this->detector->isDependencyAllowed($from, $to);

        $this->assertSame($expected, $result);
    }

    public static function dependencyAllowedProvider(): array
    {
        return [
            // Same layer always allowed
            [LayerDetector::LAYER_CONTROLLER, LayerDetector::LAYER_CONTROLLER, true],
            [LayerDetector::LAYER_DOMAIN, LayerDetector::LAYER_DOMAIN, true],

            // Controller can depend on anything
            [LayerDetector::LAYER_CONTROLLER, LayerDetector::LAYER_APPLICATION, true],
            [LayerDetector::LAYER_CONTROLLER, LayerDetector::LAYER_DOMAIN, true],
            [LayerDetector::LAYER_CONTROLLER, LayerDetector::LAYER_INFRASTRUCTURE, true],

            // Application cannot depend on Controller
            [LayerDetector::LAYER_APPLICATION, LayerDetector::LAYER_CONTROLLER, false],
            [LayerDetector::LAYER_APPLICATION, LayerDetector::LAYER_DOMAIN, true],

            // Domain should be pure - cannot depend on Infrastructure or Application
            [LayerDetector::LAYER_DOMAIN, LayerDetector::LAYER_INFRASTRUCTURE, false],
            [LayerDetector::LAYER_DOMAIN, LayerDetector::LAYER_APPLICATION, false],
            [LayerDetector::LAYER_DOMAIN, LayerDetector::LAYER_CONTROLLER, false],

            // Infrastructure can depend on Domain
            [LayerDetector::LAYER_INFRASTRUCTURE, LayerDetector::LAYER_DOMAIN, true],
            [LayerDetector::LAYER_INFRASTRUCTURE, LayerDetector::LAYER_CONTROLLER, false],
        ];
    }

    public function testDetectWithSymfonyProjectType(): void
    {
        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('Symfony');

        // Symfony-specific patterns
        $layer = $this->detector->detectLayer('App\\EventSubscriber\\UserSubscriber', $projectType);
        $this->assertSame(LayerDetector::LAYER_APPLICATION, $layer);

        $layer = $this->detector->detectLayer('App\\MessageHandler\\SendEmailHandler', $projectType);
        $this->assertSame(LayerDetector::LAYER_APPLICATION, $layer);
    }

    public function testDetectWithLaravelProjectType(): void
    {
        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('Laravel');

        // Laravel-specific patterns
        $layer = $this->detector->detectLayer('App\\Http\\Controllers\\UserController', $projectType);
        $this->assertSame(LayerDetector::LAYER_CONTROLLER, $layer);

        $layer = $this->detector->detectLayer('App\\Jobs\\SendEmailJob', $projectType);
        $this->assertSame(LayerDetector::LAYER_APPLICATION, $layer);
    }

    public function testConstants(): void
    {
        $this->assertSame('Controller', LayerDetector::LAYER_CONTROLLER);
        $this->assertSame('Application', LayerDetector::LAYER_APPLICATION);
        $this->assertSame('Domain', LayerDetector::LAYER_DOMAIN);
        $this->assertSame('Infrastructure', LayerDetector::LAYER_INFRASTRUCTURE);
        $this->assertSame('Other', LayerDetector::LAYER_OTHER);
    }
}
