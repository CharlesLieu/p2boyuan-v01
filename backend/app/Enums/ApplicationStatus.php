<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_ASSIGNMENT = 'PENDING_ASSIGNMENT';
    case ASSIGNED = 'ASSIGNED';
    case INSPECTION_IN_PROGRESS = 'INSPECTION_IN_PROGRESS';
    case PENDING_REVIEW = 'PENDING_REVIEW';
    case NEEDS_SUPPLEMENT = 'NEEDS_SUPPLEMENT';
    case REJECTED = 'REJECTED';
    case PENDING_PAYOUT = 'PENDING_PAYOUT';
    case PAID = 'PAID';
    case COMPLETED = 'COMPLETED';
}
