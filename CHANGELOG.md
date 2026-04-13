# Changelog

All notable changes to this project will be documented in this file.

## [2.1.1](https://github.com/jorisnoo/craft-cloudinary/releases/tag/v2.1.1) (2026-04-13)

### Features

- add dry-run and force flags with deletion safeguards to sync ([feb23da](https://github.com/jorisnoo/craft-cloudinary/commit/feb23dacfa603cf3d24ca486b120d77a5311f65e))

### Bug Fixes

- strip external Cloudinary package update notifications ([09983ee](https://github.com/jorisnoo/craft-cloudinary/commit/09983eebc10c4c7d75b6412a2db5af8225948350))
## [2.1.0](https://github.com/jorisnoo/craft-cloudinary/releases/tag/v2.1.0) (2026-04-09)

### Features

- add Cloudinary volume sync functionality ([e980844](https://github.com/jorisnoo/craft-cloudinary/commit/e980844e279f3a75376dd912530b024fc329181f))
- add Cloudinary API rate limit monitoring command and utility ([ef274e2](https://github.com/jorisnoo/craft-cloudinary/commit/ef274e2d81a87f65157e914f945cadcd477a7b3e))
- add icon-mask.svg asset for cloudinary utility ([5f199a2](https://github.com/jorisnoo/craft-cloudinary/commit/5f199a252bad5bb0eb31482c6f648e665a209b8a))
- add activity logging and cloudinary utility page ([1202e0d](https://github.com/jorisnoo/craft-cloudinary/commit/1202e0d2e61aec6a1b670bc0a965cc9dc71c2ba5))

### Bug Fixes

- only compare dimensions for image resources in metadata reconciliation ([2a7f21a](https://github.com/jorisnoo/craft-cloudinary/commit/2a7f21ac7b2d051ec6216c1e762db258bfc6b4c7))

### Code Refactoring

- remove Illuminate Str dependency and simplify path formatting ([65764be](https://github.com/jorisnoo/craft-cloudinary/commit/65764be3f38880e25f9b416193bb5afaab52f0ce))
- remove CloudinaryApi service and craft event sync handling ([db84ed9](https://github.com/jorisnoo/craft-cloudinary/commit/db84ed9f8d44f1ff5c47b7d3b6ed8158b039fc4d))
- consolidate sync and reconcile actions into single search api workflow ([1cb7785](https://github.com/jorisnoo/craft-cloudinary/commit/1cb77856c661820aab9504b36378982665227a1e))
- remove activity log service in favor of webhook log ([0047490](https://github.com/jorisnoo/craft-cloudinary/commit/0047490206111046a3933a50da6deb3ea96202fc))
- replace thumbnail caching with webhook-based asset reconciliation ([7e61bf0](https://github.com/jorisnoo/craft-cloudinary/commit/7e61bf0ff9aa1687ad671c175271474b8ebd7b37))
- simplify image transform detection using reflection and update plugin configuration ([d3104a5](https://github.com/jorisnoo/craft-cloudinary/commit/d3104a5f9ce5305f74e22ce200c7fd98dffc17a3))

### Documentation

- update sync command documentation and fix search expression ([e6b68a1](https://github.com/jorisnoo/craft-cloudinary/commit/e6b68a19cae50f14f7f1ca7f7715784574bc1614))
## [2.0.0](https://github.com/jorisnoo/craft-cloudinary/releases/tag/v2.0.0) (2026-03-31)

### ⚠ BREAKING CHANGES

- require Craft CMS 5.0.0 and craftcms/flysystem 2.0 ([b344177](https://github.com/jorisnoo/craft-cloudinary/commit/b3441774b21f7e68029fc57e44768b67348b762c))

### Features

- resolve cloudinary volume from webhook signature ([cdbd871](https://github.com/jorisnoo/craft-cloudinary/commit/cdbd87157fda2fe88bbeea3fc1d655935cca2df6))
- implement thumbnail caching system for Cloudinary assets ([ea55780](https://github.com/jorisnoo/craft-cloudinary/commit/ea55780dcce14be65eef1812fe2f6d5778a4a137))
- require Craft CMS 5.0.0 and craftcms/flysystem 2.0 ([b344177](https://github.com/jorisnoo/craft-cloudinary/commit/b3441774b21f7e68029fc57e44768b67348b762c))

### Code Refactoring

- simplify asset sync to run directly in CLI without queue ([4fd0509](https://github.com/jorisnoo/craft-cloudinary/commit/4fd0509c115fdde8c1250490651f33a886f83ef8))
- make thumbnail pending marking atomic and move timestamp verification ([18f164b](https://github.com/jorisnoo/craft-cloudinary/commit/18f164b6b17d1c9bee629ddc074844e79e103045))
- implement pending thumbnail tracking and optimize HTTP caching ([20469b2](https://github.com/jorisnoo/craft-cloudinary/commit/20469b2d757fa42a1642297b391d563de7c474b0))
- move thumbnail caching to async queue job ([12719a8](https://github.com/jorisnoo/craft-cloudinary/commit/12719a8754d9fa8565523d439d3eeb2b03095779))

### Tests

- add Pest testing framework with unit tests and CI workflow ([a8fa88c](https://github.com/jorisnoo/craft-cloudinary/commit/a8fa88c8c553b589d77937041636f6fd0f83a79a))

### Continuous Integration

- auto-merge all dependabot PRs ([6e4dfa2](https://github.com/jorisnoo/craft-cloudinary/commit/6e4dfa26148fe115fb406b2a38d719dbbecb9cf2))

### Chores

- remove phpstan script and minimum-stability configuration ([224d895](https://github.com/jorisnoo/craft-cloudinary/commit/224d89584073b8fc5a205bdd0edfbe3fdfe5c22e))
- upgrade Cloudinary dependencies to v3 and stabilize flysystem-cloudinary package ([ca270eb](https://github.com/jorisnoo/craft-cloudinary/commit/ca270ebca7be2f84456983766795a1268c219e7a))
## [1.9.1](https://github.com/jorisnoo/craft-cloudinary/releases/tag/v1.9.1) (2026-03-10)

### Features

- support syncing all Cloudinary volumes and add optional volume ID parameter to sync command ([8df7da5](https://github.com/jorisnoo/craft-cloudinary/commit/8df7da52aaa1d6bd04c4f1c4a4b5aa97a0af1f6f))
## [1.9.0](https://github.com/jorisnoo/craft-cloudinary/releases/tag/v1.9.0) (2026-03-10)

### Features

- add structured logging, sensitive data masking, daily log rotation, and transform mode mapping ([fd7ea24](https://github.com/jorisnoo/craft-cloudinary/commit/fd7ea248a8d6e16267317f88c91d2e7d2c90f7f4))

### Bug Fixes

- rename plugin handle from _cloudinary to cloudinary ([d6be850](https://github.com/jorisnoo/craft-cloudinary/commit/d6be850f88a1bf936503f83222d5f848361b730b))
- derive public_id from asset path instead of unreliable extra metadata ([1129adf](https://github.com/jorisnoo/craft-cloudinary/commit/1129adf7f63726f64f0d24c08cd9f631cf26faf9))

### Code Refactoring

- clean up logging verbosity, fix namespace casing, add return types, and extract shared helpers ([c069bbf](https://github.com/jorisnoo/craft-cloudinary/commit/c069bbf54b48737b5811ee49c9e6c034870e219b))
- rename namespace to Noo\CraftCloudinary, update package metadata, docs, and release workflow ([d9b8e79](https://github.com/jorisnoo/craft-cloudinary/commit/d9b8e7908c31b7c36bf81f9498b9fe4638bca525))
