<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Test/dev kernel for the bundle development.
 * This is NOT part of the distributed bundle.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
