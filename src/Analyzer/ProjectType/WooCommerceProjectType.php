<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\ProjectType;

/**
 * WooCommerce plugin
 */
class WooCommerceProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'woocommerce';
    }

    public function getLabel(): string
    {
        return 'WooCommerce';
    }

    public function getDescription(): string
    {
        return 'WooCommerce extension plugin';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        if ($this->hasComposerPackage($projectPath, 'woocommerce/woocommerce')) {
            $score += 50;
        }

        // Check for WooCommerce header in plugin file
        $phpFiles = glob($projectPath . '/*.php') ?: [];
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            if ($content !== false) {
                if (str_contains($content, 'WC requires at least:') ||
                    str_contains($content, 'woocommerce')) {
                    $score += 30;
                    break;
                }
            }
        }

        // WooCommerce specific directories/patterns
        if ($this->dirExists($projectPath, 'includes/admin')) {
            $score += 15;
        }

        // Check for WC_ prefixed classes
        $srcFiles = glob($projectPath . '/includes/*.php') ?: [];
        foreach ($srcFiles as $file) {
            $content = file_get_contents($file);
            if ($content !== false && preg_match('/class\s+WC_/', $content)) {
                $score += 20;
                break;
            }
        }

        return min($score, 100);
    }

    public function getExcludedPaths(): array
    {
        return array_merge(parent::getExcludedPaths(), [
            'assets',
            'languages',
            'templates',
        ]);
    }

    public function getClassCategories(): array
    {
        return [
            'WC_.*Gateway.*' => 'PaymentGateway',
            'WC_.*Shipping.*' => 'ShippingMethod',
            'WC_.*Email.*' => 'Email',
            'WC_.*Admin.*' => 'Admin',
            'WC_.*Product.*' => 'Product',
            'WC_.*Order.*' => 'Order',
            'WC_.*Cart.*' => 'Cart',
            'WC_.*Checkout.*' => 'Checkout',
            '.*Integration$' => 'Integration',
        ];
    }

    public function getRecommendedThresholds(): array
    {
        return [
            'ccn' => 12,
            'lcom' => 0.8,
            'mi' => 20,
        ];
    }
}
