<?php

declare(strict_types=1);

namespace Ollieread\ComposerLocalRepositories;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonValidationException;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Seld\JsonLint\ParsingException;

class LocalRepositoriesPlugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;
    /**
     * @var array{"ignore-options"?: string[], "trigger-commands"?: string[]|null}
     */
    private array $config;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::COMMAND => 'loadRepositories',
        ];
    }

    public function loadRepositories(CommandEvent $event): void
    {
        $config = $this->getConfig();
        $triggerCommands = (array)($config['trigger-commands'] ?? ['install', 'update']);
        $ignoreOptions = (array)($config['ignore-options'] ?? ['no-dev', 'prefer-source']);
        $testOptions = array_intersect_key($event->getInput()->getOptions(), array_flip($ignoreOptions));

        if (
            array_filter($testOptions) !== []
            || !in_array($event->getCommandName(), $triggerCommands, true)
        ) {
            return;
        }

        // Get root path according to Composer (when providing the path to the binary).
        $rootPath = dirname($this->composer->getConfig()->getConfigSource()->getName());
        $packagesPath = $rootPath . DIRECTORY_SEPARATOR . 'repositories.json';

        if (!file_exists($packagesPath)) {
            $this->io->write('<info>No local repositories.json available</info>', true, IOInterface::VERBOSE);
            return;
        }

        $this->io->write('<info>Local repositories.json available</info>');
        $this->loadPackagesFrom($packagesPath);
    }

    private function loadPackagesFrom(string $path): void
    {
        try {
            $json = new JsonFile($path);
            $json->validateSchema(JsonFile::LAX_SCHEMA, __DIR__ . '/../resources/composer-repositories-schema.json');
            $contents = $json->read();
        } catch (JsonValidationException|ParsingException $e) {
            $this->io->writeError($e->getMessage());
            return;
        }

        if (isset($contents['repositories'])) {
            // The reverse is important so that their priority matches the order
            // they're defined in.
            $repositories = array_reverse($contents['repositories']);

            $this->io->write('<info>Repositories found:</info> ' . count($repositories), true, IOInterface::VERBOSE);

            foreach ($repositories as $name => $config) {
                $this->loadRepository($name, $config);
            }
        }
    }

    /**
     * @param array{type: ?string, url: string } $config
     */
    private function loadRepository(int|string $name, array $config): void
    {
        $manager = $this->composer->getRepositoryManager();

        $repository = $manager->createRepository(
            $config['type'] ?? 'path', // If there's no type, it's path-based
            $config,
            is_string($name) ? $name : null,
        );

        $this->io->write('<info>Adding repository:</info> ' . $repository->getRepoName());

        $manager->prependRepository($repository);

        $this->io->write('Repository added successfully', true, IOInterface::VERBOSE);
    }

    /**
     * @return array{"ignore-options"?: string[], "trigger-commands"?: string[]}
     */
    private function getConfig(): array
    {
        if (!isset($this->config)) {
            $this->config = [];

            $global_composer_file_path = $this->composer->getConfig()->get('home') . '/composer.json';
            if (!file_exists($global_composer_file_path)) {
                return $this->config = [];
            }

            try {
                $global_config = (new JsonFile($global_composer_file_path))->read();
                $this->config = $global_config['extra']['local-repositories'] ?? [];
            } catch (ParsingException $e) {
                $this->config = [];
            }
        }

        return $this->config;
    }


    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // This is intentionally empty
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // This is intentionally empty
    }
}
