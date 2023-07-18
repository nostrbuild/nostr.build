<br />
<a href="https://bunny.net?ref=pji59zr7a4">
    <img alt="Bunny CDN Logo" src="https://bunny.net/v2/images/bunnynet-logo-dark.svg" width="300" />
</a>

# BunnyNet API client for PHP

<div align="left">
    <img src="https://img.shields.io/packagist/v/toshy/bunnynet-php?label=Packagist" alt="Current bundle version" />
    <img src="https://img.shields.io/packagist/dt/toshy/bunnynet-php?label=Downloads" alt="Packagist Total Downloads" />
    <img src="https://img.shields.io/packagist/php-v/toshy/bunnynet-php?label=PHP" alt="PHP version requirement" />
    <img src="https://img.shields.io/badge/PSR-18-brightgreen" alt="PHP-FIG PSR-18" />
    <img src="https://img.shields.io/github/actions/workflow/status/toshy/bunnynet-php/phpcs.yml?branch=master&label=PHPCS" alt="Code style">
    <img src="https://img.shields.io/github/actions/workflow/status/toshy/bunnynet-php/phpmd.yml?branch=master&label=PHPMD" alt="Mess detector">
    <img src="https://img.shields.io/github/actions/workflow/status/toshy/bunnynet-php/phpstan.yml?branch=master&label=PHPStan" alt="Static analysis">
    <img src="https://img.shields.io/github/actions/workflow/status/toshy/bunnynet-php/phpunit.yml?branch=master&label=PHPUnit" alt="Unit tests">
    <img src="https://img.shields.io/github/actions/workflow/status/toshy/bunnynet-php/security.yml?branch=master&label=Security" alt="Security">
</div>

<a href="https://bunny.net?ref=pji59zr7a4">Bunny.net<a/> is content delivery platform that truly hops: providing CDN,
edge storage, video streaming, image optimizers and much more!

<small>
<b>Note</b>: This is a non-official library for the <a href="https://docs.bunny.net/docs">bunny.net API</a>.
</small>

## 🧰 Install

```bash
composer require toshy/bunnynet-php:^3.0
```

> Note: The `2.x` is not longer actively maintained. See [UPGRADE.md](./UPGRADE.md) for upgrade instructions.


## 📜 Documentation

Full documentation is available at [https://toshy.github.io/BunnyNet-PHP](https://toshy.github.io/BunnyNet-PHP).

## 🛠️ Contribute

Features and bugfixes should be based on the `master` branch.

### Prerequisites

* [Docker Compose](https://docs.docker.com/compose/install/)
* [Task (optional)](https://taskfile.dev/installation/)

### Install dependencies

```shell
task composer:install 
```

### Enable GrumPHP

```shell
task grum:init
```

> Note: Checks for `phpcs`, `phpstan`, `phpmd` and `phpunit` are executed when committing. 
> You can also run these checks with `task contribute`.

## ❕ Licence

This repository comes with a [MIT license](./LICENSE).
