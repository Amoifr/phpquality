<?php

declare(strict_types=1);

namespace PhpQuality;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class PhpQualityBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
