<?php

declare(strict_types=1);

namespace Survos\DocBundle\Command;

use Playwright\Playwright;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand('doc:screenshot', 'take screenshot')]
final class ScreenshotCommand
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument] string $url,
        #[Argument] string $screenshotPath = '',
        #[Option(description: 'use .wip sites')] bool $dev = false,
        #[Option(description: 'local directory')] string $dir = 'public/casts/',
    ): int {
        if (!$screenshotPath) {
            $screenshotPath = (new \DateTime('now'))->format('Y-m-d-H-i-s');
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $screenshotPath = $dir.$screenshotPath.'.png';

        $io->warning($url);

        if (!class_exists(Playwright::class)) {
            $io->error('Screenshots need playwright-php/playwright: composer require --dev playwright-php/playwright && vendor/bin/playwright-install chromium');

            return Command::FAILURE;
        }

        // Real Chromium through Playwright. The Symfony local proxy is only needed for .wip hosts (it serves their
        // local TLS certificate, hence ignoreHTTPSErrors); any other URL goes straight out.
        $options = ['headless' => true, 'ignoreHTTPSErrors' => true];
        if (str_ends_with((string) parse_url($url, \PHP_URL_HOST), '.wip')) {
            $options['proxy'] = ['server' => 'http://127.0.0.1:7080'];
        }
        $context = Playwright::chromium($options);
        try {
            $page = $context->newPage();
            $page->setViewportSize(1500, 4000);
            $page->goto($url);
            $page->screenshot($screenshotPath);
        } finally {
            $context->close();
        }
        $base = '';
        $link = $screenshotPath;
        //        $signUpPage = $this->urlGenerator->generate('sign_up');
        $io->writeln("Screenshot of $url at <href=$base/$link>$screenshotPath</>");
        $uri = str_replace('public/', '', $screenshotPath);
        $io->writeln("symfony open:local --path=$uri");

        return Command::SUCCESS;
    }
}
