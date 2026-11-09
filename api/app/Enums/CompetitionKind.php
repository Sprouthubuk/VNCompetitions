<?php

namespace App\Enums;

enum CompetitionKind: string
{
    use HasValues;

    case Darts = 'darts';
    case Board = 'board';
    case Ring = 'ring';
    case Box = 'box';
    case Voucher = 'voucher';
}
