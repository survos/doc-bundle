<?php

namespace Survos\DocBundle\Services;

use KnpU\ODoc2ClientBundle\Client\ClientRegistry;
use KnpU\ODoc2ClientBundle\DependencyInjection\ProviderFactory;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Yaml\Yaml;

class BaseService
{

    public function __construct(
        private array $config,
//        private ProviderFactory $provider
)
    {
//        $this->clientRegistry = $clientRegistry;
//        $this->provider = $provider;
//        $this->userClass = $userClass;
    }

    public function allowLogin(): bool
    {
        return $this->config['allow_login'] !== 'none';
    }

    public function getUserClass(): string
    {
        return $this->userClass;
    }

    public function getOdocClientKeys(): ?array {
        return $this->clientRegistry?->getEnabledClientKeys();
    }

    // hack to get client id
    private function accessProtected($obj, $prop) {
        $reflection = new \ReflectionClass($obj);
        // dump($obj, $prop);
        try {
            $property = $reflection->getProperty($prop);
            $property->setAccessible(true);
            return $property->getValue($obj);
        } catch (\Exception $e) {
            // log the error?
            return false;
        }
    }

    public function getOdocClients($all = false): array {
        // links to accont info
        $providers = $this->getODocProviderUrls();

        // really need to get all TYPES (facebookId, etc.), then group the clients under them, plus have the provider data.
        // eventually we'll want an admin client to display the related apps

        $keys = $this->clientRegistry->getEnabledClientKeys();


        return array_combine($keys, array_map(function ($key) use ($providers) {
                $client = $this->clientRegistry->getClient($key);
                $provider = $providers[$key];
                $clientId = $this->accessProtected($client->getODoc2Provider(), 'clientId');
                $type = $this->accessProtected($client->getODoc2Provider(), 'type');
            try {
            } catch (\Exception $e) {
                $client = false;
                $provider = false;
            }
            // $extra = $this->accessProtected($provider, 'extrias');
            return [
                'key' => $key,
                'provider' => $provider,
                'client' => $client,
                'appId' => $clientId,
                // 'type' => $type
            ];
        }, $keys) );
    }

    public function getClientRegistry(): ClientRegistry
    {
        return $this->clientRegistry;
    }


    // the hand-curated list of URLs.  Written by hand
    protected static function getODocProviderUrlPath(): string
    {
        return __DIR__ . '/../data/odoc_provider_urls.yaml';
    }

    // the data from KNPU's list of providers plus the urls to link to for configurating.
    // written by survos:fetch-odoc-providers,
    // when read by BaseService::docProviderData.  Does NOT include project-specific data
    // when read by BaseService->docProviderConfigurationData.  Does include project-specific data (needs ClientRegistry)
    protected static function getODocProviderCombinedPath(): string
    {
        return __DIR__ . '/../data/odoc_provider.yaml';
    }

    public function writeCombinedOdocData($data)
    {
        file_put_contents($fn = self::getODocProviderCombinedPath(),
            sprintf("#  automatically recreated, merges data from knp odoc client bundle  and %s\n\n%s",
                self::getODocProviderUrlPath(), Yaml::dump(['providers' => $data], 3, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)) );

        return $fn;

    }

    // returns array of providers, by key.  Includes a 'clients' key with configured clients
    public function getCombinedOdocData(): array
    {
        $providers = Yaml::parseFile(self::getODocProviderCombinedPath())['providers'];
        // set some defaults
        $resolver = (new OptionsResolver())
            ->setDefaults([
                'clients' => [],
                'user_apps' => null,
                'app_url' => null,
                'apps_url' => null,
                'admin_url' => null,
                'user_url' => null,
                'comments' => null,
                'client_id' => null,
                'client_secret' => null,
                'class' => null,
                'library' => null,
                'type' => null,
            ]);

        $classToTypeMap = [];
        foreach (array_keys($providers) as $key) {
            $provider = $resolver->resolve($providers[$key]);

            // map the class to the type, since the client information lacks a type

            // get the environment vars
            $comments = $provider['comments'];
            if (preg_match_all('/env\(([^\)]+)\)/', $comments, $m)) {
                $provider['env_vars'] = $m[1];
            } else {
                die("Bad Match " . $comments);
            }

            $classToTypeMap[$provider['class']] = $provider['type'];

            // hack to fix the routes
            $comments = str_replace(sprintf('connect_%s_check', $key), 'odoc_connect_check', $comments);
            $comments = str_replace('redirect_params: {}', "redirect_params: { clientKey: $key}  # MUST match the client key above", $comments);
            $provider['comments'] = $comments;

            $providers[$key] = $provider;
            // throw new \Exception($provider, $classToTypeMap);

        }
        /* not sure why this doesn't work
        array_walk(array_keys($providers), function ($key) use ($providers, $resolver) {
            // $providers[$key]['clients'] = [];
            // $data['clients'] = [];
            $providers[$key] = $resolver->resolve($providers[$key]);
            if ($key === 'amazon') dump($providers[$key]);
        });
        */

        // go through all the providers and look for their TYPE
        $providerClients = [];
        foreach ($this->clientRegistry->getEnabledClientKeys() as $key) {
            $client = $this->clientRegistry->getClient($key);
            $clientProvider = $client->getODoc2Provider();
            $class = get_class($client); // the Knpu class
            $providerClass = get_class($clientProvider);

            $type = $classToTypeMap[$class];
            if (!key_exists($type, $providers)) {
                throw new \Exception("Missing $type ($class) in providers");
            }
            // throw new \Exception($class, $client, $clientProvider, $type, $providerClass);

            $providers[$type]['clients'][$key]  = $client;
        }

        /*
        $clients = array_map(function ($key) use ($providers) {
            return [
                'key' => $key,
                'config' => $providers[$key],
                'clients' => $providerClients[strtolower($key)] ?? false
            ];
        }, array_keys($providers));
        */
        return $providers;
    }

    public function getODocProviderUrls()
    {
        return Yaml::parseFile(self::getODocProviderUrlPath())['providers'];
    }


}

