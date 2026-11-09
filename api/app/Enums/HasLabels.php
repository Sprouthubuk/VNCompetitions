<?php

namespace App\Enums;

use ValueError;

/**
 * For enums whose public (API) form is a label, e.g. Category::InstantWin => "Instant win".
 */
trait HasLabels
{
    abstract public function label(): string;

    public static function fromLabel(string $label): self
    {
        foreach (self::cases() as $case) {
            if ($case->label() === $label) {
                return $case;
            }
        }

        throw new ValueError("\"{$label}\" is not a valid label for ".self::class);
    }
}
