<?php

namespace Survos\DocBundle;

use Survos\DocBundle\Command\SurvosBuildDocsCommand;
use Survos\DocBundle\Command\UploadCommand;
use Survos\DocBundle\Controller\ScreenshotController;
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

    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->autowire(ScreenshotController::class)
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('controller.service_arguments')
            ->addTag('controller.service_subscriber');

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

        $builder->autowire(UploadCommand::class)
            ->setArgument('$httpClient', new Reference('http_client'))
            ->setArgument('$projectDir', '%kernel.project_dir%')
//            ->setArgument('$config', $config)
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
