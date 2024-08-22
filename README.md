# Composer Local Repositories

The composer plugin to quickly add local repositories for development purposes, without the need to update
your `composer.json` file.

## How to install

```bash
composer require --global ollieread/composer-local-repositories
```

## How to use

Add a `repositories.json` file to any project that you want to add a custom repository to. The file needs to contain a
valid composer `repositories` key. For example:

**`repositories.json`**

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../local-folder"
    }
  ]
}
```

During a `composer install` or `composer update`, the plugin will locate the `repositories.json` file; and prepend all
the configured repositories. If composer finds any of the `require` package inside these repositories, it will install
the package from that repository instead.

## Configuration

To configure the plugin, you can provide extra configuration keys under a `local-repositories` key in the `extra`
section.

- `trigger-commands` An array of composer commands that loads the local `repositories.json` file (default: `install`
  and `update`)
- `ignore-flags` An array of flags on which to ignore the local `repositories.json` file (default: `--no-dev`
  and `--prefer-source`)
- `force-dev` Whether to update the constraint of any found packages from local repositories with `@dev` (
  default: `true`)

Full configuration example with default values:

**global `composer.json`**

```json
{
  "extra": {
    "local-repositories": {
      "trigger-commands": [
        "install",
        "update"
      ],
      "ignore-flags": [
        "no-dev",
        "prefer-source"
      ],
      "force-dev": true
    }
  }
}
```
