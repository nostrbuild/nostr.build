# Respect\Stringifier

[![Build Status](https://img.shields.io/github/actions/workflow/status/Respect/Stringifier/continuous-integration.yml?branch=master&style=flat-square)](https://github.com/Respect/Stringifier/actions/workflows/continuous-integration.yml)
[![Code Coverage](https://img.shields.io/codecov/c/github/Respect/Stringifier?style=flat-square)](https://codecov.io/gh/Respect/Stringifier)
[![Latest Stable Version](https://img.shields.io/packagist/v/respect/stringifier.svg?style=flat-square)](https://packagist.org/packages/respect/stringifier)
[![Total Downloads](https://img.shields.io/packagist/dt/respect/stringifier.svg?style=flat-square)](https://packagist.org/packages/respect/stringifier)
[![License](https://img.shields.io/packagist/l/respect/stringifier.svg?style=flat-square)](https://packagist.org/packages/respect/stringifier)

Converts any PHP value into a string.

## Installation

Package is available on [Packagist](https://packagist.org/packages/respect/stringifier), you can install it
using [Composer](http://getcomposer.org).

```bash
composer require respect/stringifier
```

This library requires PHP >= 8.1.

## Feature Guide

Below a quick guide of how to use the library.

### Namespace import

Respect\Stringifier is namespaced, and you can make your life easier by importing
a single function into your context:

```php
use function Respect\Stringifier\stringify;
```

Stringifier was built using objects, the `stringify()` is a easy way to use it.

### Usage

Simply use the function to convert any value you want to:

```php
echo stringify($value);
```

To see more examples of how to use the library check the [integration tests](tests/integration).
