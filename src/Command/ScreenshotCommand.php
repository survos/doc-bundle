<?php

namespace Survos\DocBundle\Command;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Panther\Client;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
#[AsCommand('doc:screenshot', 'take screenshot')]
final class ScreenshotCommand
{

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function __invoke(
        SymfonyStyle                                  $io,
        #[Argument] string                            $url,
        #[Argument] string                            $screenshotPath='',
        #[Option(description: 'use .wip sites')] bool $dev = false,
        #[Option(description: 'local directory')] string $dir='public/casts/'
    ): int
    {
        if (!$screenshotPath) {
            $screenshotPath = (new \DateTime('now'))->format('Y-m-d-H-i-s');
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $screenshotPath = $dir . $screenshotPath . '.png';

//        dump($_SERVER, $this->urlGenerator->generate('#'));
        // of interest: https_proxy=$(symfony proxy:url) curl https://my-domain.wip
//        $client = Client::createChromeClient();
        $client = Client::createChromeClient(
            null,
            [
            '--window-size=1500,4000',
            '--proxy-server=http://127.0.0.1:7080'
            ]
        );
        //let s use firefox
        //$client = Client::createFirefoxClient();
        $host = parse_url($url, PHP_URL_HOST);
        $io->warning($url);
        $client->request('GET', $url);
        $client->takeScreenshot($screenshotPath);
        $base = '';
        $link = $screenshotPath;
//        $signUpPage = $this->urlGenerator->generate('sign_up');
        $io->writeln("Screenshot of $url at <href=$base/$link>$screenshotPath</>");
        $uri = str_replace('public/', '', $screenshotPath);
        $io->writeln("symfony open:local --path=$uri");

        return Command::SUCCESS;

    }
}
