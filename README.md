# Dockr CLI

[![Build Status](https://travis-ci.org/dugajean/dockr-cli.svg?branch=master)](https://travis-ci.org/dugajean/dockr-cli) 
[![Latest Stable Version](https://poser.pugx.org/dugajean/dockr-cli/v/stable)](https://packagist.org/packages/dugajean/dockr-cli) 
[![Total Downloads](https://poser.pugx.org/dugajean/dockr-cli/downloads)](https://packagist.org/packages/dugajean/dockr-cli) 
[![License](https://poser.pugx.org/dugajean/dockr-cli/license)](https://packagist.org/packages/dugajean/dockr-cli) 

Easy Docker Compose setup for your LAMP and LEMP projects.

## Requirements

- Docker & docker-compose
- PHP 7+
- `ext-json`
- `ext-ctype`

## Download

###### For direct use

To download the latest release, head over to [Releases](https://github.com/dugajean/dockr-cli/releases) and pick the latest PHAR. Then:

```bash
$ dockr.phar --version
```

Feel free to move this to `/usr/local/bin` and remove the `.phar` extension.

###### Per project installation

```bash
$ composer require dugajean/dockr-cli --dev
```

```bash
$ vendor/bin/dockr --version
```

## Testing

```bash
$ vendor/bin/phpunit
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License
Pouch is released under [the MIT License](LICENSE).

## Support on Beerpay
Hey dude! Help me out for a couple of :beers:!

[![Beerpay](https://beerpay.io/dugajean/dockr-cli/badge.svg?style=beer-square)](https://beerpay.io/dugajean/dockr-cli)  [![Beerpay](https://beerpay.io/dugajean/dockr-cli/make-wish.svg?style=flat-square)](https://beerpay.io/dugajean/dockr-cli?focus=wish)
