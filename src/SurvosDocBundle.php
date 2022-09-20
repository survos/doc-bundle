<?php

namespace Survos\DocBundle;

use Survos\DocBundle\Command\SurvosBuildDocsCommand;
use Survos\DocBundle\EventSubscriber\LoggerSubscriber;
use Survos\DocBundle\Twig\TwigExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SurvosDocBundle extends AbstractBundle
{

    protected string $extensionAlias = 'survos_doc';

    /** @param array<mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {

        $definition = $builder
            ->autowire('survos.doc_twig', TwigExtension::class)
            ->addTag('twig.extension')
            ->setArgument('$config', $config)
            ;

//        $definition->setArgument('$seed', $config['seed']);
//        $definition->setArgument('$prefix', $config['function_prefix']);

        $builder->autowire(SurvosBuildDocsCommand::class)
            ->setArgument('$config', $config)
            ->setArgument('$twig', new Reference('twig'))
            ->addTag('console.command')
        ;

        $definition = $builder->autowire(LoggerSubscriber::class)
            ->setArgument('$config', $config)
            ->setArgument('$options', [])
            ->setArgument('$twig', new Reference('twig'))
        ;
        $definition
            ->addMethodCall('setTwig', [new Reference('twig')]);


    }

    public function configure(DefinitionConfigurator $definition): void
    {
        // since the configuration is short, we can add it here
        $definition->rootNode()
            ->children()
            ->scalarNode('user_provider')->defaultValue(null)->end()
            ->scalarNode('user_class')->defaultValue("App\\Entity\\User")->end()
            ->end();
        ;
    }

}
