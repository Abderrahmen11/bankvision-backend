<?php

namespace App\Enums;

/**
 * Staff roles for BankVision.
 *
 * Single source of truth for role strings — use these cases instead of
 * string literals when comparing or validating roles in application code.
 * (Route middleware parameters must remain literal strings by Laravel design.)
 */
enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Compliance = 'compliance';
    case Analyst = 'analyst';
    case Csr = 'csr';
    case Auditor = 'auditor';

    /**
     * All role values, e.g. for validation rules: 'in:' . implode(',', Role::values()).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Roles whose writes are scoped to their assigned branch
     * (branch managers and customer service representatives).
     *
     * @return list<string>
     */
    public static function branchScoped(): array
    {
        return [self::Manager->value, self::Csr->value];
    }

    /**
     * Roles that create/record customer-facing data at branch desks.
     *
     * @return list<string>
     */
    public static function operational(): array
    {
        return [self::Admin->value, self::Manager->value, self::Csr->value];
    }

    /**
     * Roles with approval authority over money movement (transactions, loans).
     *
     * @return list<string>
     */
    public static function approvers(): array
    {
        return [self::Admin->value, self::Manager->value];
    }

    /**
     * Roles allowed to flag transactions / file SAR escalations.
     *
     * @return list<string>
     */
    public static function flaggers(): array
    {
        return [self::Admin->value, self::Manager->value, self::Compliance->value];
    }

    /**
     * Roles with read access to compliance-relevant audit trails.
     *
     * @return list<string>
     */
    public static function auditReaders(): array
    {
        return [self::Admin->value, self::Auditor->value, self::Compliance->value, self::Manager->value];
    }

    /**
     * Roles with analytical dashboard/report read access (excludes CSR).
     *
     * @return list<string>
     */
    public static function analysts(): array
    {
        return [self::Admin->value, self::Manager->value, self::Compliance->value, self::Analyst->value, self::Auditor->value];
    }
}
