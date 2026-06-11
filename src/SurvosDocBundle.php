<?php

namespace Survos\DocBundle;

use Doctrine\ORM\EntityManagerInterface;
use Survos\DocBundle\Service\EntityDocCollector;
use Survos\DocBundle\Twig\TwigExtension;
use Survos\FieldBundle\Registry\EntityMetaRegistry;
use Survos\Kit\AbstractSurvosBundle;
use Survos\Kit\Traits\HasConfigurableRoutes;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

class SurvosDocBundle extends AbstractSurvosBundle
{
    use HasConfigurableRoutes;

    protected string $extensionAlias = 'survos_doc';

    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $this->addRouteOptions($children, '/doc');
        $children
            ->scalarNode('user_provider')->defaultValue(null)->end()
            ->scalarNode('user_class')->defaultValue("App\\Entity\\User")->end()
            // What to document. Each section names the namespaces (or exact command/workflow
            // names) to include; an empty include list means "all". More sections (workflow,
            // …) will be added here as doc-bundle grows.
            ->arrayNode('console')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('include')
                        ->scalarPrototype()->end()
                    ->end()
                ->end()
            ->end()
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
        // AbstractSurvosBundle auto-registers src/Command/ and src/Controller/
        // (autowire + autoconfigure), so commands and controllers wire themselves
        // up — no per-class registration. They read config via #[Autowire] on the
        // parameters below.
        parent::loadExtension($config, $container, $builder);

        $this->captureRouteConfig($config);
        $this->registerRouteLoader($builder);

        $builder->setParameter('survos_doc.console.include', $config['console']['include'] ?? []);

        // TwigExtension lives in src/Twig (outside the auto-scanned dirs), so it
        // still needs an explicit definition.
        $builder
            ->autowire('survos.doc_twig', TwigExtension::class)
            ->addTag('twig.extension')
            ->setArgument('$config', $config);

        // Used by doc:entities / doc:readme. Both the EntityManager and field-bundle's
        // registry are optional (null when the app has no Doctrine / field-bundle), so
        // the bundle never breaks an app that lacks them.
        $builder->autowire(EntityDocCollector::class)
            ->setArgument('$em', new Reference(EntityManagerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$registry', new Reference(EntityMetaRegistry::class, ContainerInterface::NULL_ON_INVALID_REFERENCE));
    }
}
