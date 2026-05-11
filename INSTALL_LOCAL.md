# Local Development Setup

This guide explains how to use TalkBridge locally before publishing to Packagist.

---

## Method 1 — Composer path repository (recommended)

Place the package folder anywhere on your machine. Then tell your Laravel app
where to find it.

### Step 1 — Copy the package into your Laravel project

```
your-laravel-app/
└── packages/
    └── rahatulrabbi/
        └── talkbridge/       ← paste the package contents here
```

### Step 2 — Edit your Laravel app's composer.json

Open the **host app's** `composer.json` (not the package's) and add:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/rahatulrabbi/talkbridge",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "rahatulrabbi/talkbridge": "*"
    }
}
```

### Step 3 — Install

```bash
composer update rahatulrabbi/talkbridge
```

### Step 4 — Verify ServiceProvider is loaded

```bash
php artisan package:discover --ansi
```

You should see:
```
Discovered Package: rahatulrabbi/talkbridge
```

### Step 5 — Run the install wizard

```bash
php artisan talkbridge:install
```

---

## Method 2 — Manual ServiceProvider registration (Laravel 11+)

If auto-discovery does not work, register manually in `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    RahatulRabbi\TalkBridge\TalkBridgeServiceProvider::class,
];
```

Then run:

```bash
php artisan talkbridge:install
```

---

## Troubleshooting

### "There are no commands defined in the talkbridge namespace"

This means the ServiceProvider was not loaded. Fix in order:

1. Confirm `composer update` ran successfully:
   ```bash
   composer update rahatulrabbi/talkbridge
   ```

2. Check the package is in `vendor/`:
   ```bash
   ls vendor/rahatulrabbi/talkbridge
   ```

3. Clear caches:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   composer dump-autoload
   ```

4. Run discovery:
   ```bash
   php artisan package:discover --ansi
   ```

5. If still failing — add to `bootstrap/providers.php` manually (Method 2 above).

### "Class not found" errors

```bash
composer dump-autoload
```

### Symlink not working on Windows

Use `"symlink": false` in the repository options:

```json
"options": {
    "symlink": false
}
```
