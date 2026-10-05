<?php

namespace App\Services\Dashboard\StatusReport;

use App\Enums\Roles;

/**
 * Maps status-report channels to the role IDs that belong in each channel.
 *
 * Channels:
 * - internal: collectors and senior collectors
 * - external: external office users
 * - legal: legal department users
 */
class StatusReportChannels
{
    /**
     * Role IDs for the internal channel.
     *
     * @return list<int>
     */
    public static function internal(): array
    {
        return [
            Roles::COLLECTOR->value,
            Roles::SENIOR_COLLECTOR->value,
        ];
    }

    /**
     * Role IDs for the external channel.
     *
     * @return list<int>
     */
    public static function external(): array
    {
        return [
            Roles::EXTERNAL_OFFICE->value,
        ];
    }

    /**
     * Role IDs for the legal channel.
     *
     * @return list<int>
     */
    public static function legal(): array
    {
        return [
            Roles::LEGAL_DEPARTMENT->value,
        ];
    }
}
