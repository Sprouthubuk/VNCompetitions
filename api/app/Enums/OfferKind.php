<?php

namespace App\Enums;

enum OfferKind: string
{
    use HasValues;

    case BuyXGetY = 'buy_x_get_y';
    case Multiplier = 'multiplier';
}
