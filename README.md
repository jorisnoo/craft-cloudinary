# Cloudinary for Craft CMS

This plugin integrates [Cloudinary](https://cloudinary.com/) with [Craft CMS](https://craftcms.com/). Assets can be uploaded from Craft's control panel and then transformed and delivered by Cloudinary, even if stored in a different filesystem. The plugin is compatible with your existing Craft template code and named image transforms.

This is a fork of [thomasvantuycom/craft-cloudinary](https://github.com/thomasvantuycom/craft-cloudinary) with additional features including webhook synchronization, daily log rotation, and improved error handling.

## Version Strategy

| Branch    | Version | Craft CMS |
|-----------|---------|-----------|
| `main`    | 2.x     | 5.x       |
| `craft-4` | 1.x     | 4.x       |

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later

## Installation

```bash
composer require jorisnoo/craft-cloudinary
./craft plugin/install cloudinary
```

## Setup

The plugin adds a Cloudinary filesystem type to Craft. It can be used solely as a transform filesystem or as a storage filesystem as well.

To create a new Cloudinary filesystem, visit **Settings** → **Filesystems**, press **New filesystem**, and select "Cloudinary" as the **Filesystem Type**. Configure your Cloud Name, API Key, and API Secret (environment variables are supported).

To start using the filesystem, visit **Settings** → **Assets** → **Volumes**. You can create a new volume using the Cloudinary filesystem for both storage and transforms, or add the Cloudinary filesystem to any existing volume for transforms only. In the latter case, assets with public URLs from any filesystem are transformed by Cloudinary using the [fetch feature](https://cloudinary.com/documentation/fetch_remote_images#fetch_and_deliver_remote_files).

## Image Transformations

The plugin supports all of [Craft's native transform options](https://craftcms.com/docs/5.x/system/image-transforms.html), including mode (fit, letterbox, stretch, crop), position, quality, and format.

You can also use any of [Cloudinary's transformation options](https://cloudinary.com/documentation/transformation_reference#overview) in template transforms:

```twig
{% set thumb = {
  width: 100,
  height: 100,
  quality: 75,
  opacity: 33,
  border: '5px_solid_rgb:999',
} %}

<img src="{{ asset.getUrl(thumb) }}">
```

Transformation options should be in camelCase (`aspect_ratio` → `aspectRatio`, `fetch_format` → `fetchFormat`).

## Webhook Notifications

The plugin supports real-time synchronization with Cloudinary via webhooks. When assets are uploaded, deleted, renamed, or moved in Cloudinary, the changes are automatically reflected in Craft.

### Setup

Go to your [Cloudinary webhook settings](https://console.cloudinary.com/settings/webhooks) and add a notification URL:

```
https://your-site.com/actions/cloudinary/notifications/process?volume={VOLUME_ID}
```

Replace `{VOLUME_ID}` with your asset volume ID.

### Supported notification types

- `upload` — creates assets in Craft
- `delete` — removes assets from Craft
- `rename` — updates filenames
- `move` / `move_or_rename_asset_folder` — moves assets between folders
- `resource_display_name_changed` — updates asset titles
- `create_folder` / `delete_folder` — manages folder structure

### Security

Webhook requests are verified using Cloudinary's HMAC-SHA1 signature (via `X-Cld-Signature` and `X-Cld-Timestamp` headers). Signatures older than 2 hours are rejected.

## Console Commands

```bash
# Reconcile all Cloudinary asset volumes using the Search API
php craft cloudinary/sync

# Scan and fix public IDs that contain folder paths
php craft cloudinary/remove-paths-from-public-ids/scan {volumeId}
```

The sync command auto-detects all Cloudinary volumes and reconciles them with the Cloudinary Search API. It compares metadata (size, dimensions) and creates, updates, or deletes assets as needed. Ideal for cron jobs or catching up after missed webhooks.

## Logging

The plugin logs to `storage/logs/cloudinary-YYYY-MM-DD.log` with daily rotation (30 days retained, 10MB max per file). Sensitive data (API keys, signatures) is automatically masked in logs.
