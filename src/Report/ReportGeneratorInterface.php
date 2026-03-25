<?php

declare(strict_types=1);

namespace PhpQuality\Report;

use PhpQuality\Analyzer\Result\ProjectResult;

interface ReportGeneratorInterface
{
    /**
     * Generate a report from the analysis result
     */
    public function generate(ProjectResult $result, mixed $output): void;
}
