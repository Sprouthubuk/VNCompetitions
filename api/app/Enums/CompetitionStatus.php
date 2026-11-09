<?php

namespace App\Enums;

enum CompetitionStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case ComingSoon = 'coming_soon';
    case Live = 'live';
    case SoldOut = 'sold_out';
    case Closed = 'closed';
    case Drawn = 'drawn';
    case Cancelled = 'cancelled';
}
