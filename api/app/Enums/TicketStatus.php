<?php

namespace App\Enums;

enum TicketStatus: string
{
    use HasValues;

    case Held = 'held';
    case Sold = 'sold';
    case Postal = 'postal';
    case Free = 'free';
}
