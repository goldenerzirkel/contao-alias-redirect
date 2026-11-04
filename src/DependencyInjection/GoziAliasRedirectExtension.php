<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class GoziAliasRedirectExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('gozi_alias_redirect.ohne_endungen', $config['ohne_endungen']);

        (new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config')))->load('services.yaml');
    }
}
