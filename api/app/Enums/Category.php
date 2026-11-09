<?php

namespace App\Enums;

enum Category: string
{
    use HasValues;

    case InstantWin = 'instant_win';
    case Darts = 'darts';
    case Boards = 'boards';
    case MysteryBoxes = 'mystery_boxes';
    case Vouchers = 'vouchers';

    public function label(): string
    {
        return match ($this) {
            self::InstantWin => 'Instant win',
            self::Darts => 'Darts',
            self::Boards => 'Boards',
            self::MysteryBoxes => 'Mystery boxes',
            self::Vouchers => 'Vouchers',
        };
    }
}
