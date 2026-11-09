<?php

namespace App\Enums;

enum InstantGame: string
{
    use HasValues;

    case Classic = 'classic';
    case Dartboard = 'dartboard';
    case Wheel = 'wheel';
}
