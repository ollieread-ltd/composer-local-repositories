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
        if (
            !in_array($event->getCommandName(), ['install', 'update'], true)
            || $event->getInput()->getOption('no-dev')
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

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // This is intentionally empty
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // This is intentionally empty
    }
}
