<?php

namespace App\Enums;

enum ExportType: string
{
    case EXPORT_CLAIMS = 'export_claims';
    case EXPORT_MESSAGES = 'export_messages';
    case EXPORT_IVRS = 'export_ivrs';
    case EXPORT_IMPORT_REQUESTS = 'export_import_requests';
    case EXPORT_PERMISSION_REQUESTS = 'export_permission_requests';
    case EXPORT_CLIENT_REQUESTS = 'export_client_requests';
    case EXPORT_PAYMENT_TRANSACTIONS = 'export_payment_transactions';
    case EXPORT_DELEGATIONS = 'export_delegations';
    case EXPORT_TARGETS = 'export_targets';
    case EXPORT_TARGET_REPORT = 'export_target_report';
    case EXPORT_COLLECTOR_TARGETS = 'export_collector_targets';
    case EXPORT_TARGET_DATA = 'export_target_data';
    case EXPORT_ACCOUNTING_DATA = 'export_accounting_data';
    case EXPORT_AUTHENTICATION_LOGS = 'export_authentication_logs';
    case EXPORT_DEFAULT_ON_PAYMENT = 'export_default_on_payment';
    case EXPORT_ASSIGNED_CLAIMS = 'export_assigned_claims';
    case EXPORT_USERS = 'export_users';
    case EXPORT_TRASHED_USERS = 'export_trashed_users';
    case EXPORT_ROLES = 'export_roles';
}
