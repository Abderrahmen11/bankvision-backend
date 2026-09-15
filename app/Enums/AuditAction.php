<?php

namespace App\Enums;

/**
 * Centralized audit action names — the single source of truth shared by the
 * backend (observers + explicit logging) and consumed by the frontend to
 * render badges consistently.
 */
enum AuditAction: string
{
    // Generic model lifecycle (model observers)
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    // Auth
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case PasswordChanged = 'password_changed';

    // Two-factor authentication
    case TwoFactorEnabled = '2fa_enabled';
    case TwoFactorDisabled = '2fa_disabled';
    case TwoFactorVerified = '2fa_verified';

    // Domain lifecycle
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Flagged = 'flagged';
    case Assigned = 'assigned';
    case Resolved = 'resolved';

    // Security
    case TokenIssued = 'token_issued';
    case TokenRevoked = 'token_revoked';
    case SessionRevoked = 'session_revoked';

    // Settings
    case SystemSettingsUpdated = 'system_settings_updated';
    case CurrencyUpdated = 'currency_updated';
    case PreferencesUpdated = 'preferences_updated';
    case NotificationsUpdated = 'notifications_updated';

    // Dashboard
    case LayoutSaved = 'layout_saved';
    case LayoutReset = 'layout_reset';
}
