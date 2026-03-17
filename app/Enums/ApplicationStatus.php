<?php

namespace App\Enums;

class ApplicationStatus
{
    const SUBMITTED = 'submitted';
    const APPROVED_SCHOOL = 'approved_school';
    const REJECTED_SCHOOL = 'rejected_school';
    const APPROVED_COMPANY = 'approved_company';
    const REJECTED_COMPANY = 'rejected_company';

    public static function all()
    {
        return [
            self::SUBMITTED,
            self::APPROVED_SCHOOL,
            self::REJECTED_SCHOOL,
            self::APPROVED_COMPANY,
            self::REJECTED_COMPANY,
        ];
    }
}
