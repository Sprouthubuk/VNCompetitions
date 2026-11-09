<?php

namespace App\Enums;

enum DrawKind: string
{
    use HasValues;

    case EndPrize = 'end_prize';
    case Standard = 'standard';
}
