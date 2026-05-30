<?php

namespace App\Enums;

enum UserRole: string
{
    case STORE = 'STORE';
    case SALES = 'SALES';
    case AUDITOR = 'AUDITOR';
    case CASHIER = 'CASHIER';
    case SUPER_ADMIN = 'SUPER_ADMIN';
}
