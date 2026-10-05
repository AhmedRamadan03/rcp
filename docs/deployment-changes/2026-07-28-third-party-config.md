# Deployment Changes Log

## Change: Backfill ThirdParty config.execution_type

**Date:** 2026-07-28  
**Author:** Abdulrahman Ali  
**Environment:** Production  
**Type:** Data migration (Artisan command)

### Description
A new Artisan command `third-parties:update-execution-type` was added to backfill
the `execution_type` value inside the `config` JSON column for existing
`third_parties` records.

The command reads the expected `execution_type` from the application configuration:

```php
config("third-party.{type}.{vendor_label}.execution_type")
```

and updates each record's `config.execution_type` if it is missing or differs from
the configured value.

Records without a configured `execution_type` are skipped and reported.

### Action Required by DevOps

Run the following command **after** the deployment of this release completes:

```bash
php artisan third-parties:update-execution-type --dry-run
```

Review the output and verify the records that will be updated, then execute:

```bash
php artisan third-parties:update-execution-type
```


