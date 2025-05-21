<?php

namespace Survos\DocBundle\Command;

use Nadar\PhpComposerReader\ComposerReader;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use function Symfony\Component\String\u;

#[AsCommand(
    name: 'survos:build-docs',
    description: 'Compile .rst.twig files',
)]
class SurvosBuildDocsCommand
{
    public function __construct(
        private Environment $twig,
        #[Autowire('%kernel.project_dir%/')] private string $projectDir,
        private array $config = [],
        ?string $name = null
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: "template dir")] string $templateDir = 'templates/',
        #[Argument(description: "doc template dir within templates")] string $subdir = 'docs/',
        #[Option] string $outputDir='./doc',
    ): int
    {
        $reader = new ComposerReader($this->projectDir . 'composer.json');

        $dir = $this->projectDir . $templateDir;

        $finder = new Finder();
        // create the doc templates dir if it doesn't exist
        $dir = $dir . $subdir;
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $readmeTemplateFile = $dir . 'README.md.twig';
        if (!file_exists($readmeTemplateFile)) {
            if (file_exists($existingReadmeFilename = $this->projectDir . 'README.md')) {
                $readmeTemplate = '
{% extends "@SurvosDoc/readme_base.md.twig" %}
{% block title composer.name %}
{% block body %}
' . file_get_contents($existingReadmeFilename) . '
{% endblock %}';
file_put_contents($readmeTemplateFile, $readmeTemplate);
            }
        }
        $finder->files()->in($dir);

        $outputDir = rtrim($outputDir, '/') . '/';
        foreach ($finder as $file) {
            $name = $reader->getContent()['name'];
            $shortName = u($name)->after('/')->toString();
            $rendered = $this->twig->render($subdir . $file->getBasename(), [
                'shortName' => $shortName,
                'name' => $name,
                'reader' => $reader,
                'composer' => $reader->getContent(),
            ]);

            $outputFilename = (($file->getBasename() === 'README.md.twig')
                ? $this->projectDir
                : $outputDir)  .
                $file->getBasename('.twig');

            file_put_contents($outputFilename, $rendered);
            $io->success("$outputFilename written.", true);
        }

        $io->success("Templates compiled to " . $outputDir);

        return Command::SUCCESS;
    }
}
