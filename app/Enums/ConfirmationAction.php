<?php

namespace App\Enums;

enum ConfirmationAction: string
{
    case Sent = 'sent';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case ReminderSent = 'reminder_sent';
    case Expired = 'expired';
    case AdminOverride = 'admin_override';
}
