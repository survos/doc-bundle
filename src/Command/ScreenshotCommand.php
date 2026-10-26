<?php

declare(strict_types=1);

namespace Survos\DocBundle\Command;

use Playwright\Configuration\PlaywrightConfigBuilder;
use Playwright\PlaywrightFactory;
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
        #[Option(description: 'Browser: chrome (your installed Google Chrome) or chromium (Playwright\'s own download)')] string $channel = 'chrome',
    ): int {
        if (!$screenshotPath) {
            $screenshotPath = (new \DateTime('now'))->format('Y-m-d-H-i-s');
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $screenshotPath = $dir.$screenshotPath.'.png';

        $io->warning($url);

        if (!class_exists(PlaywrightFactory::class)) {
            $io->error('Screenshots need playwright-php/playwright: composer require --dev playwright-php/playwright (it drives your installed Google Chrome; --channel=chromium needs vendor/bin/playwright-install chromium)');

            return Command::FAILURE;
        }

        // Chrome through Playwright: your installed Google Chrome by default (--channel=chromium for Playwright's own
        // download). The Symfony local proxy is only needed for .wip hosts (it serves their local TLS certificate, hence
        // ignoreHTTPSErrors); any other URL goes straight out.
        $config = PlaywrightConfigBuilder::create()->withHeadless(true);
        if ('chromium' !== $channel) {
            $config->withChannel($channel);
        }
        if (str_ends_with((string) parse_url($url, \PHP_URL_HOST), '.wip')) {
            $config->withProxy('http://127.0.0.1:7080');
        }
        try {
            $playwright = PlaywrightFactory::create($config->build()); // keep it alive: dropping it stops the Node server
            $browser = $playwright->chromium()->launch();
            try {
                $page = $browser->newContext(['ignoreHTTPSErrors' => true, 'viewport' => ['width' => 1500, 'height' => 4000]])->newPage();
                $page->goto($url);
                $page->screenshot($screenshotPath);
            } finally {
                $browser->close();
            }
        } catch (\Throwable $e) {
            $io->error(sprintf("Could not take the screenshot (browser channel '%s'): %s\nInstall Google Chrome, or use --channel=chromium after: vendor/bin/playwright-install chromium", $channel, $e->getMessage()));

            return Command::FAILURE;
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
