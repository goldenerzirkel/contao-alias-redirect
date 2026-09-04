<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Gozi\AliasRedirectBundle\GoziAliasRedirectBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [BundleConfig::create(GoziAliasRedirectBundle::class)->setLoadAfter([ContaoCoreBundle::class])];
    }
}
