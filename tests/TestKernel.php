<?php

namespace Survos\DocBundle\Tests;

use Survos\DocBundle\Command\DumpAgentDocsCommand;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Minimal kernel for command-introspection tests. doc-bundle's full extension
 * hard-references the `twig` service, which isn't a dev dependency here, so the
 * test registers only what DumpAgentDocsCommand needs (KernelInterface + Filesystem)
 * rather than booting the whole bundle. Config is inlined — there's little of it.
 */
class TestKernel extends BaseKernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'http_method_override' => false,
            ]);

            // $namespaces empty means "document everything"; the rest autowires.
            $container->autowire(DumpAgentDocsCommand::class)
                ->setArgument('$namespaces', [])
                ->setArgument('$projectDir', '%kernel.project_dir%')
                ->addTag('console.command');
        });
    }

    public function getProjectDir(): string
    {
        return __DIR__ . '/project-dir';
    }
}
