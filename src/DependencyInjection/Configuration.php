<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * gozi_alias_redirect: … in config/config.yaml des Projekts.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $baum = new TreeBuilder('gozi_alias_redirect');

        $baum->getRootNode()
            ->children()
                ->arrayNode('ohne_endungen')
                    ->info('Dateiendungen, die nie in die 404-Liste kommen, z. B. [php, asp, aspx, env]. Bilder, CSS, JS und Schriften sind ohnehin ausgenommen.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
            ->end()
        ;

        return $baum;
    }
}
