# Deployment Changes Log

## Change: Fix ThirdParty vendor_name casing for GIG SMS vendor

**Date:** 2026-07-16
**Author:** Abdulrahman Ali
**Environment:** Production
**Type:** Data fix (Artisan command)

### Description
A new Artisan command `thirdparty:fix-gig-vendor-name` was added to correct
`third_parties` records where `type = 'sms'` and `vendor_name = 'GIG'`.
The value should be normalized to `Gig` to match the expected casing used
elsewhere in the codebase/integration.

### Action Required by DevOps
Run the following command **after** the deployment of this release completes:

```bash
php artisan thirdparty:fix-gig-vendor-name --dry-run
```

Review the output, confirm the listed record(s) are correct, then run:

```bash
php artisan thirdparty:fix-gig-vendor-name
```

### Scope of Impact
- Table: `third_parties`
- Filter: `type = 'sms' AND vendor_name = 'GIG'`
- Field updated: `vendor_name` → `Gig`
- No schema changes. No downtime required.

### Rollback
If needed, the change can be reverted manually via:

```sql
UPDATE public.third_parties
SET vendor_name = 'GIG'
WHERE type = 'sms' AND vendor_name = 'Gig';
```

