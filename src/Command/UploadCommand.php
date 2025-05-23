<?php

namespace Survos\DocBundle\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand('doc:upload', 'upload a Asciinema file or directory to shellshow site', aliases: ['upload'])]
class UploadCommand
{
    public function __construct(
        private readonly HttpClientInterface                        $httpClient,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    )
    {
    }


    public function __invoke(
        SymfonyStyle                                                      $io,
        #[Argument('path to file or directory')] string                   $path,
        #[Option(name: 'server-url', description: 'api endpoint')] string $apiEndpoint = 'https://showcase.wip/api/asciicasts',
    ): int
    {

        if (!file_exists($path)) {
            $path = $this->projectDir . $path;
        }
        if (!file_exists($path)) {
            $io->error("$path does not exist");
            return Command::FAILURE;
        }
        $fileHandle = fopen($path, 'r');

        $response = $this->httpClient->request('POST', $apiEndpoint, [
            'proxy' => '127.0.0.1:7080',
            'body' => ['asciicast' => $fileHandle]]);

        dump($response->getContent());
        $io->success(self::class . " success.");
        return Command::SUCCESS;
    }
}
