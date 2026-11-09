<?php

namespace App\Enums;

enum PrizeTier: string
{
    use HasValues;

    case Premium = 'premium';
    case Darts = 'darts';
    case Accessories = 'accessories';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Premium => 'Premium',
            self::Darts => 'Darts',
            self::Accessories => 'Accessories',
            self::Credit => 'Credit',
        };
    }
}
