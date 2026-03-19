<?php

declare(strict_types=1);

namespace App\Analyzer\Metric;

interface MetricInterface
{
    /**
     * Get the metric name
     */
    public function getName(): string;

    /**
     * Get the metric description
     */
    public function getDescription(): string;

    /**
     * Calculate the metric from visitor data
     *
     * @param array<string, mixed> $visitorData
     */
    public function calculate(array $visitorData): MetricResult;
}
