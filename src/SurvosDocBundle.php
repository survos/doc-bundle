<?php

namespace Survos\DocBundle;

use Survos\DocBundle\Command\SurvosBuildDocsCommand;
use Survos\DocBundle\Command\UserCreateCommand;
use Survos\DocBundle\Controller\ODocController;
use Survos\DocBundle\Services\BaseService;
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
//        $serviceId = 'survos_doc.base_service';
//        $container->services()->alias(BaseService::class, $serviceId);
//        $builder->autowire($serviceId, BaseService::class)
//            ->setArgument('$userClass', $config['user_class'])
//            ->setArgument('$clientRegistry', new Reference('knpu.odoc2.registry'))
//            ->setArgument('$config', $config)
////            ->setArgument('$registry', new Reference('doctrine'))
////            ->setArgument('$provider', new Reference('security.user_providers'))
//            ->setPublic(true)
//            ;

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
