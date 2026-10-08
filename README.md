# Lemonade Admin Image

[![PHPStan](https://github.com/johnnyxlemonade/admin-image/actions/workflows/phpstan.yml/badge.svg)](https://github.com/johnnyxlemonade/admin-image/actions/workflows/phpstan.yml)
[![Tests](https://github.com/johnnyxlemonade/admin-image/actions/workflows/phpunit.yml/badge.svg)](https://github.com/johnnyxlemonade/admin-image/actions/workflows/phpunit.yml)
[![Coding Standards](https://github.com/johnnyxlemonade/admin-image/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/johnnyxlemonade/admin-image/actions/workflows/coding-standards.yml)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](composer.json)

`johnnyxlemonade/admin-image` provides image identifiers, variant definitions,
and public image delivery for Lemonade Framework applications. It exposes
`ImageAssetResolverInterface` for resolving source images and registers the
public endpoint that delivers a declared variant.

The package does not own upload persistence, file lifecycle, or a media
catalogue. Those responsibilities belong to the consuming platform or host
application. Image variants are declarative delivery definitions; the resolver
remains authoritative for whether a source image is available.

## Installation

The package requires PHP `>=8.3 <8.6` and `johnnyxlemonade/framework`.

```bash
composer require johnnyxlemonade/admin-image:dev-main
```

Register `Lemonade\Image\ImagePackageServiceProvider` in the host application,
bind `ImageAssetResolverInterface` to the owner of image metadata, and register
the image variants the host is allowed to deliver.

## Package boundaries

- `johnnyxlemonade/framework` owns the base runtime and image processing primitives.
- Admin Image owns public image identifiers, variant registration, and delivery.
- The consuming platform or host owns original persistence, authorization, and source availability.

## Development and QA

From the package root, run:

```bash
composer cs:check
composer stan
composer test
composer qa
```
