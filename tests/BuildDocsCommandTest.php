<?php

namespace Survos\DocBundle\Tests;

use Survos\DocBundle\Command\SurvosBuildDocsCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Twig\Environment;

class BuildDocsCommandTest extends KernelTestCase
{
    public function testExecute()
    {
        $kernel = self::bootKernel();
        $twigEnv = $this->createMock(Environment::class);

        $application = new Application($kernel);
        $application->add(new SurvosBuildDocsCommand($twigEnv, []));

        $outputDir = __DIR__ . '/test-data/output/';

        $command = $application->find('survos:build-docs');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'template-dir' => __DIR__ . '/../templates',
            'template-subdir' => '',
            '--output-dir' => $outputDir
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("Templates compiled, now run cd {$outputDir}", $output);
    }
}
