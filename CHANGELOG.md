# Changelog

All notable changes to this project will be documented in this file.

## [1.9.0](https://github.com/jorisnoo/craft-cloudinary/releases/tag/v1.9.0) (2026-03-10)

### Features

- add structured logging, sensitive data masking, daily log rotation, and transform mode mapping ([fd7ea24](https://github.com/jorisnoo/craft-cloudinary/commit/fd7ea248a8d6e16267317f88c91d2e7d2c90f7f4))

### Bug Fixes

- rename plugin handle from _cloudinary to cloudinary ([d6be850](https://github.com/jorisnoo/craft-cloudinary/commit/d6be850f88a1bf936503f83222d5f848361b730b))
- derive public_id from asset path instead of unreliable extra metadata ([1129adf](https://github.com/jorisnoo/craft-cloudinary/commit/1129adf7f63726f64f0d24c08cd9f631cf26faf9))

### Code Refactoring

- clean up logging verbosity, fix namespace casing, add return types, and extract shared helpers ([c069bbf](https://github.com/jorisnoo/craft-cloudinary/commit/c069bbf54b48737b5811ee49c9e6c034870e219b))
- rename namespace to Noo\CraftCloudinary, update package metadata, docs, and release workflow ([d9b8e79](https://github.com/jorisnoo/craft-cloudinary/commit/d9b8e7908c31b7c36bf81f9498b9fe4638bca525))
