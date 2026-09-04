<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class GoziAliasRedirectBundle extends Bundle
{
    /** Bundle-Wurzel ist der Ordner über src/ — so findet Contao contao/dca und contao/languages. */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
