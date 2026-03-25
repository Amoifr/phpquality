<?php

declare(strict_types=1);

namespace PhpQuality\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class PhpQualityExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');

        $container->setParameter(
            'phpquality.resources_path',
            dirname(__DIR__) . '/Resources'
        );
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
