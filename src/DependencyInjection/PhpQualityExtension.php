<?php

declare(strict_types=1);

namespace PhpQuality\DependencyInjection;

use PhpQuality\Config\ThresholdsConfig;
use PhpQuality\DataCollector\PhpQualityDataCollector;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class PhpQualityExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');

        $container->setParameter(
            'phpquality.resources_path',
            dirname(__DIR__) . '/Resources'
        );

        // Register thresholds configuration
        $this->registerThresholdsConfig($container, $config['thresholds']);

        // Register profiler data collector if enabled
        if ($config['profiler']['enabled']) {
            $this->registerDataCollector($container, $config['profiler']);
        }
    }

    private function registerThresholdsConfig(ContainerBuilder $container, array $thresholds): void
    {
        $definition = new Definition(ThresholdsConfig::class, [
            $thresholds['ccn'],
            $thresholds['lcom'],
            $thresholds['mi'],
        ]);

        $container->setDefinition(ThresholdsConfig::class, $definition);

        // Also set as parameters for backward compatibility
        $container->setParameter('phpquality.thresholds.ccn', $thresholds['ccn']);
        $container->setParameter('phpquality.thresholds.lcom', $thresholds['lcom']);
        $container->setParameter('phpquality.thresholds.mi', $thresholds['mi']);
    }

    private function registerDataCollector(ContainerBuilder $container, array $profilerConfig): void
    {
        $definition = new Definition(PhpQualityDataCollector::class, [
            new Reference('PhpQuality\Analyzer\FileAnalyzer'),
            new Reference(ThresholdsConfig::class),
            '%kernel.project_dir%',
            $profilerConfig['exclude_paths'],
        ]);

        $definition->setPublic(true);
        $definition->addTag('data_collector', [
            'template' => '@PhpQuality/data_collector/phpquality.html.twig',
            'id' => 'phpquality',
            'priority' => 300,
        ]);

        $container->setDefinition(PhpQualityDataCollector::class, $definition);
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Add bundle templates path
        $container->prependExtensionConfig('twig', [
            'paths' => [
                dirname(__DIR__) . '/Resources/views' => 'PhpQuality',
            ],
        ]);

        // Add bundle translations path
        $container->prependExtensionConfig('framework', [
            'translator' => [
                'paths' => [
                    dirname(__DIR__) . '/Resources/translations',
                ],
            ],
        ]);
    }
}
