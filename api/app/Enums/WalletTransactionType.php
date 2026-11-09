<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    use HasValues;

    case Prize = 'prize';
    case Refund = 'refund';
    case Goodwill = 'goodwill';
    case Spend = 'spend';
    case Expiry = 'expiry';
    case Reversal = 'reversal';
}
