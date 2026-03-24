# Upgrade Guide

This document outlines how to upgrade between versions.

## 1.x → Future Versions

### General Steps

1. Update the package:

```bash
composer update xul/laravel-project-map
```
2. Review configuration changes:
```php
php artisan vendor:publish --tag=project-map-config --force
```

⚠️ Always review changes before overwriting your config.

3. Clear cache:
```php
php artisan config:clear
php artisan cache:clear
```

## Breaking Changes

Any breaking changes will be documented here in future releases.