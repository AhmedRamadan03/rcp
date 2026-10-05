# Deployment Changes Log

## Change: Remove File Links from SMS

**Date:** 2026-09-15
**Author:** Abdulrahman Ali
**Environment:** Production
**Type:** Data Fix (Database Seeder)

### Description

We will rely on the Client View to provide access to claim files instead of sending file links via SMS. Therefore, the message template category needs to be updated to remove the file URL variable.

### Action Required by DevOps

Run the following command **after the deployment of this release is completed**:

```bash
php artisan db:seed --class=CategoryMessageVariablesSeeder
```
