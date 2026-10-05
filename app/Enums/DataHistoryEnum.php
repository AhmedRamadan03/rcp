<?php

namespace App\Enums;

enum DataHistoryEnum: string
{
    //status
    case PROCESSING = 'processing';
    case READY = 'ready';
    case CANCELLED = 'cancelled';


    //Types
    case EXPORT = 'export';
    case IMPORT = 'import';


    //Titles
    case ACCOUNT_REPORT = 'Account Report';
    case CLAIM_EXPORT = 'Claims export';
    case ASSIGNED_CLAIMS = 'Assigned Claims';
    case IMPORT_NEW_CLAIMS = 'Import New Claims';
    case IMPORT_BACKLOG_CLAIMS = 'Import Backlog Claims';
    case EXPORT_DEFAULT_ON_PAYMENT_REPORT = 'Export Default On Payment Report';
    case EXPORT_MESSAGES = 'Export Messages';
    case EXPORT_IVRS = 'Export IVRS';
    case EXPORT_IMPORT_HISTORIES = 'Export / Import Histories';
    case EXPORT_PERMISSION_REQUESTS = 'Export Permission Requests';
    case EXPORT_REJECTED_CLAIMS = 'Export Client Requests';
    case EXPORT_TRANSACTION_HISTORY = 'Export Transaction History';
    case EXPORT_DELEGATION_HISTORY = 'Export Delegation History';
    case EXPORT_TARGETS = 'Export Targets';
    case EXPORT_TARGET_REPORT = 'Export Target Report';
    case EXPORT_TARGET_DATA = 'Export Target Data';
    case EXPORT_ASSIGNED_CLAIMS_DATA = 'Export Assigned Claims Data';
    case EXPORT_AUTHENTICATION_LOG_HISTORY = 'Export Authentication Log History';
    case EXPORT_USERS = 'Export Users';
    case EXPORT_TRASHED_USERS = 'Export Trashed Users';
    case EXPORT_ROLES = 'Export Roles';

    //return statuses only
    public static function getReturnStatuses(): array
    {
        return [
            self::PROCESSING->value,
            self::READY->value,
            self::CANCELLED->value,
        ];
    }


    //return types only
    public static function getReturnTypes(): array
    {
        return [
            self::EXPORT->value,
            self::IMPORT->value,
        ];  
    }



}
