<?php

declare(strict_types=1);

namespace App\Report;

use App\Analyzer\Result\ProjectResult;

interface ReportGeneratorInterface
{
    /**
     * Generate a report from the analysis result
     */
    public function generate(ProjectResult $result, mixed $output): void;
}
