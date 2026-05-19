<?php

namespace Survos\DocBundle;

use Survos\Kit\Traits\HasConfigurableRoutes;
use Survos\DocBundle\Command\ScreenshotCommand;
use Survos\DocBundle\Command\SurvosBuildDocsCommand;
use Survos\DocBundle\Command\UploadCommand;
use Survos\DocBundle\Controller\ScreenshotController;
use Survos\DocBundle\Twig\TwigExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Survos\Kit\AbstractSurvosBundle;

class SurvosDocBundle extends AbstractSurvosBundle
{
    use HasConfigurableRoutes;

    protected string $extensionAlias = 'survos_doc';

    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $this->addRouteOptions($children, '/doc');
        $children
            ->scalarNode('screenshow_endpoint')->defaultValue('%env(default::SCREENSHOW_ENDPOINT)%')->end()
            ->scalarNode('user_provider')->defaultValue(null)->end()
            ->scalarNode('user_class')->defaultValue("App\\Entity\\User")->end()
        ->end();
    }

    /**
     * @param array<mixed> $config
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->addRouteLoaderCompilerPass($container);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $this->captureRouteConfig($config);
        $this->registerRouteLoader($builder);

        $builder->autowire(ScreenshotController::class)
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('controller.service_arguments')
            ->addTag('controller.service_subscriber');

        $builder
            ->autowire('survos.doc_twig', TwigExtension::class)
            ->addTag('twig.extension')
            ->setArgument('$config', $config);

        $builder->autowire(SurvosBuildDocsCommand::class)
            ->setArgument('$config', $config)
            ->setArgument('$twig', new Reference('twig'))
            ->addTag('console.command');

        $builder->autowire(ScreenshotCommand::class)
            ->addTag('console.command');

        $builder->autowire(UploadCommand::class)
            ->setArgument('$httpClient', new Reference('http_client'))
            ->setArgument('$projectDir', '%kernel.project_dir%')
            ->setArgument('$config', $config)
            ->addTag('console.command');
    }
}
