<?php

namespace Dfn\Bundle\OroCronofyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Oro\Bundle\ConfigBundle\DependencyInjection\SettingsBuilder;

/**
 * @package Dfn\Bundle\OroCronofyBundle\DependencyInjection
 * @author  Mike Napier <mike@napiercommunications.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('dfn_oro_cronofy');
        SettingsBuilder::append(
            $rootNode,
            [
                'client_id' => ['value' => null],
                'client_secret' => ['value' => null],
            ]
        );

        return $treeBuilder;
    }
}
