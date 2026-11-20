# Lemonade Admin Image

[![Tests](https://img.shields.io/github/actions/workflow/status/johnnyxlemonade/admin-image/phpunit.yml?branch=main&label=Tests&logo=githubactions&logoColor=white&labelColor=0f766e)](https://github.com/johnnyxlemonade/admin-image/actions/workflows/phpunit.yml)
[![PHP 8.3–8.6](https://img.shields.io/badge/PHP-8.3%20%7C%208.4%20%7C%208.5%20%7C%208.6-777BB4?logo=php&logoColor=white)](#requirements)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/johnnyxlemonade/admin-image/phpstan.yml?branch=main&label=PHPStan&logo=php&logoColor=white&labelColor=5b4baf)](https://github.com/johnnyxlemonade/admin-image/actions/workflows/phpstan.yml)
[![License](https://img.shields.io/badge/license-Apache--2.0-green.svg)](LICENSE)

`johnnyxlemonade/admin-image` provides variant definitions
and public image delivery for Lemonade Framework applications. It exposes
`ImageAssetResolverInterface` for resolving source images and registers the
public endpoint that delivers a declared variant.

The package does not own upload persistence, file lifecycle, or a media
catalogue. Those responsibilities belong to the consuming platform or host
application. Image variants are declarative delivery definitions; the resolver
remains authoritative for whether a source image is available.

## Requirements

- PHP 8.3 or newer, tested on PHP 8.3, 8.4, 8.5 and 8.6.
- `johnnyxlemonade/framework:dev-main`.

## Installation

```bash
composer require johnnyxlemonade/admin-image:dev-main
```

Register `Lemonade\Image\ImagePackageServiceProvider` in the host application,
bind `ImageAssetResolverInterface` to the owner of image metadata, and register
the image variants the host is allowed to deliver.

The single public URL format is `/api/image/{variant}/{fileId}`, including
`/api/image/original/{fileId}` for originals. `ImageUrlGenerator::url($variant,
$fileId)` and `originalUrl($fileId)` accept positive integer file IDs. The
resolver maps each ID to its stored asset; Admin Platform uses `system_file`.
Module codes remain internal metadata and are not part of the URL.

Admin Platform registers `admin-datagrid`, `admin-thumb`, and `admin-preview`.
Frontend variants are registered by the host. Previous URLs containing a module
code are no longer supported.

## Package boundaries

- `johnnyxlemonade/framework` owns the base runtime and image processing primitives.
- Admin Image owns variant registration and delivery by global file ID.
- The consuming platform or host owns original persistence, authorization, and source availability.

## Development and QA

From the package root, run:

```bash
composer cs:check
composer stan
composer test
composer qa
```

## Private storage

Image delivery streams files from the Framework path resolver. Persistent originals
live under `storage/uploads/images/originals`; regeneratable variants live under
`storage/cache/images`. URLs and HTTP cache validators are unchanged. Neither
variants nor originals require direct web-server access to `public/uploads`.
There is no legacy-path fallback: deployment must coordinate the physical data move.
