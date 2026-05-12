<?php

namespace App\Enum;

class NotificationType
{
    public const string PROVINCE_CREATED = 'prv_cre';
    public const string CHECKPOINT_CREATED = 'chk_cre';

    public const string TRIP_CREATED = 'trp_cre';
    public const string TRIP_UPDATED = 'trp_upd';
    public const string TRIP_CANCELLED = 'trp_cnl';

    public const string TICKET_CREATED = 'tkt_cre';
    public const string TICKET_PAYMENT_PENDING = 'tkt_pnd';
    public const string TICKET_PAYMENT_FAILED = 'tkt_payf';
    public const string TICKET_VALIDATED = 'tkt_val';
    public const string TICKET_CANCELLED = 'tkt_cnl';
    public const string TICKET_EXPIRED = 'tkt_exp';

    public const string PAYMENT_CREATED = 'pay_cre';
    public const string PAYMENT_REDIRECT_REQUIRED = 'pay_red';
    public const string PAYMENT_PAID = 'pay_paid';
    public const string PAYMENT_FAILED = 'pay_fail';

    public const string GOPASS_ISSUED = 'gps_iss';
    public const string GOPASS_VALIDATED = 'gps_val';
    public const string GOPASS_EXPIRED = 'gps_exp';
    public const string GOPASS_INVALID_SCAN = 'gps_inv';

    public const string AGENCY_CREATED = 'agn_cre';
    public const string AGENCY_UPDATED = 'agn_upd';
    public const string AGENCY_SUSPENDED = 'agn_sus';

    public const string USER_CREATED = 'usr_cre';
    public const string USER_UPDATED = 'usr_upd';
    public const string ACCOUNT_ACTIVATED = 'usr_act';
    public const string PASSWORD_RESET = 'usr_pwd';
    public const string FIRST_LOGIN_REQUIRED = 'usr_fst';
    public const string TYPE_OTP = 'usr_otp';
    public const string ROLE_ASSIGNED = 'usr_rol';

    public const string DOCUMENT_UPLOADED = 'doc_upl';
    public const string DOCUMENT_EXPIRED = 'doc_exp';
    public const string DOCUMENT_EXPIRING_SOON = 'doc_soo';

    public const string SYSTEM_UPDATE = 'sys_upd';
    public const string ERROR_REPORTED = 'sys_err';
    public const string MAINTENANCE_ALERT = 'sys_mnt';
    public const string BACKUP_COMPLETED = 'sys_bck';

    public static function getAll(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return $reflection->getConstants();
    }

    public static function getGrouped(): array
    {
        return [
            'geo' => [
                self::PROVINCE_CREATED,
                self::CHECKPOINT_CREATED,
            ],
            'trips' => [
                self::TRIP_CREATED,
                self::TRIP_UPDATED,
                self::TRIP_CANCELLED,
            ],
            'tickets' => [
                self::TICKET_CREATED,
                self::TICKET_PAYMENT_PENDING,
                self::TICKET_PAYMENT_FAILED,
                self::TICKET_VALIDATED,
                self::TICKET_CANCELLED,
                self::TICKET_EXPIRED,
            ],
            'payments' => [
                self::PAYMENT_CREATED,
                self::PAYMENT_REDIRECT_REQUIRED,
                self::PAYMENT_PAID,
                self::PAYMENT_FAILED,
            ],
            'gopass' => [
                self::GOPASS_ISSUED,
                self::GOPASS_VALIDATED,
                self::GOPASS_EXPIRED,
                self::GOPASS_INVALID_SCAN,
            ],
            'agencies' => [
                self::AGENCY_CREATED,
                self::AGENCY_UPDATED,
                self::AGENCY_SUSPENDED,
            ],
            'users' => [
                self::USER_CREATED,
                self::USER_UPDATED,
                self::ACCOUNT_ACTIVATED,
                self::PASSWORD_RESET,
                self::FIRST_LOGIN_REQUIRED,
                self::TYPE_OTP,
                self::ROLE_ASSIGNED,
            ],
            'documents' => [
                self::DOCUMENT_UPLOADED,
                self::DOCUMENT_EXPIRED,
                self::DOCUMENT_EXPIRING_SOON,
            ],
            'system' => [
                self::SYSTEM_UPDATE,
                self::ERROR_REPORTED,
                self::MAINTENANCE_ALERT,
                self::BACKUP_COMPLETED,
            ],
        ];
    }

    public static function getUserNotifications(): array
    {
        return [
            self::TICKET_CREATED,
            self::TICKET_PAYMENT_PENDING,
            self::TICKET_PAYMENT_FAILED,
            self::TICKET_VALIDATED,
            self::PAYMENT_REDIRECT_REQUIRED,
            self::PAYMENT_PAID,
            self::PAYMENT_FAILED,
            self::GOPASS_ISSUED,
            self::GOPASS_VALIDATED,
            self::GOPASS_EXPIRED,
            self::DOCUMENT_EXPIRING_SOON,
            self::DOCUMENT_EXPIRED,
        ];
    }

    public static function getAgencyNotifications(): array
    {
        return [
            self::AGENCY_CREATED,
            self::AGENCY_UPDATED,
            self::AGENCY_SUSPENDED,
            self::TRIP_CREATED,
            self::TRIP_UPDATED,
            self::TRIP_CANCELLED,
            self::TICKET_CREATED,
            self::PAYMENT_CREATED,
            self::PAYMENT_PAID,
            self::PAYMENT_FAILED,
        ];
    }

    public static function getAdminNotifications(): array
    {
        return [
            self::PROVINCE_CREATED,
            self::CHECKPOINT_CREATED,
            self::AGENCY_CREATED,
            self::AGENCY_SUSPENDED,
            self::SYSTEM_UPDATE,
            self::ERROR_REPORTED,
            self::MAINTENANCE_ALERT,
            self::BACKUP_COMPLETED,
            self::ROLE_ASSIGNED,
        ];
    }

    public static function getOwnerNotifications(): array
    {
        return self::getAgencyNotifications();
    }

    public static function getTenantNotifications(): array
    {
        return self::getUserNotifications();
    }

    public static function getCriticalNotifications(): array
    {
        return [
            self::PAYMENT_FAILED,
            self::TICKET_PAYMENT_FAILED,
            self::DOCUMENT_EXPIRED,
            self::AGENCY_SUSPENDED,
            self::GOPASS_INVALID_SCAN,
            self::ERROR_REPORTED,
        ];
    }
}
