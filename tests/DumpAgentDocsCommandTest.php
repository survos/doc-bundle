<?php

namespace Survos\DocBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DumpAgentDocsCommandTest extends KernelTestCase
{
    public function testExecute(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $dir = __DIR__ . '/project-dir/docs/command';
        $ownFile = $dir . '/docCommands.md';
        $index = $dir . '/README.md';

        $command = $application->find('doc:commands');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        // One file per command, named camelCase: doc:commands -> docCommands.md
        self::assertFileExists($ownFile);
        $contents = file_get_contents($ownFile);
        self::assertNotFalse($contents);
        // Setext heading emitted by MarkdownBuilder (name on one line, "===" underline).
        self::assertStringContainsString("doc:commands\n", $contents);
        self::assertStringContainsString('Options', $contents);
        self::assertStringContainsString('`--output-dir', $contents);
        self::assertStringNotContainsString('`--help`', $contents);
        self::assertStringNotContainsString('`--profile`', $contents);

        // Index links to the per-command file.
        self::assertFileExists($index);
        self::assertStringContainsString('(docCommands.md)', (string) file_get_contents($index));
    }
}
