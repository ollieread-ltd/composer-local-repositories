<?php
declare(strict_types=1);

namespace Ollieread\ComposerLocalRepositories;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Plugin\PluginInterface;

class LocalRepositoriesPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var \Composer\Composer
     */
    private Composer $composer;

    /**
     * @var \Composer\IO\IOInterface
     */
    private IOInterface $io;

    private array $repositories = [];

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io       = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // This is intentionally empty
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // This is intentionally empty
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'init' => 'loadRepositories',
        ];
    }

    /**
     * @return void
     * @throws \Composer\Json\JsonValidationException
     * @throws \JsonException
     */
    public function loadRepositories(): void
    {
        $rootPath     = dirname(Factory::getComposerFile());
        $packagesPath = $rootPath . DIRECTORY_SEPARATOR . 'repositories.json';

        if (! file_exists($packagesPath)) {
            $this->io->writeError('<info>No local repositories.json available</info>');
        } else {
            $this->io->writeError('<info>Local repositories.json available</info>');

            $this->loadPackagesFrom($packagesPath);
        }
    }

    /**
     * @throws \Composer\Json\JsonValidationException
     * @throws \JsonException
     */
    private function loadPackagesFrom(string $path): void
    {
        // Get the contents of the file
        $contents = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        // Validate the schema, to make sure it's valid
        JsonFile::validateJsonSchema(
            $path,
            $contents,
            JsonFile::LAX_SCHEMA,
            __DIR__ . '/../resources/composer-repositories-schema.json'
        );

        if (isset($contents['repositories'])) {
            // The reverse is important so that their priority matches the order
            // they're defined in.
            $repositories = array_reverse($contents['repositories']);

            $this->io->writeError('<info>Repositories found:</info> ' . count($repositories));

            foreach ($repositories as $name => $config) {
                $this->loadRepository($name, $config);
            }
        }
    }

    private function loadRepository(int|string $name, array $config): void
    {
        $manager = $this->composer->getRepositoryManager();

        $repository = $manager->createRepository(
            $config['type'] ?? 'path', // If there's no type, it's path-based
            $config,
            $name
        );

        $this->io->writeError('<info>Adding repository:</info> ' . $repository->getRepoName());

        $this->repositories[] = $repository;

        $manager->prependRepository($repository);

        $this->io->writeError('Repository added successfully');
    }
}
