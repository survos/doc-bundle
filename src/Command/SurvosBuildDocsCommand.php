<?php

namespace Survos\DocBundle\Command;

use Nadar\PhpComposerReader\ComposerReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use function Symfony\Component\String\u;

#[AsCommand(
    name: 'survos:build-docs',
    description: 'Compile .rst.twig files',
)]
class SurvosBuildDocsCommand extends Command
{
    public function __construct(
        private Environment $twig,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private array $config,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('template-dir', InputArgument::OPTIONAL, 'Template Directory', './templates/')
            ->addArgument('template-subdir', InputArgument::OPTIONAL, 'Template Subdirectory', 'docs/')
            ->addOption('output-dir', 'o', InputOption::VALUE_OPTIONAL, 'Output Directory (the .rst file)', './doc')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $reader = new ComposerReader($this->projectDir . '/composer.json');

        $io = new SymfonyStyle($input, $output);
        $dir = $input->getArgument('template-dir');
        $subdir = $input->getArgument('template-subdir');

        $finder = new Finder();
        $finder->files()->in($dir . $subdir);

        $outputDir =  $input->getOption('output-dir');
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
                : $outputDir) . '/' .
                $file->getBasename('.twig');

            file_put_contents($outputFilename, $rendered);
            $output->write("$outputFilename written.", true);
        }

        $io->success("Templates compiled, now run cd $outputDir && make html");

        return self::SUCCESS;
    }
}
