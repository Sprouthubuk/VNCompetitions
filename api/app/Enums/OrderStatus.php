<?php

namespace App\Enums;

enum OrderStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';
}
