<?php

namespace App\Enums;

enum CompetitionType: string
{
    use HasValues;

    case Standard = 'standard';
    case Instant = 'instant';
}
