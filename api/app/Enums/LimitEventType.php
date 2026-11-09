<?php

namespace App\Enums;

enum LimitEventType: string
{
    use HasValues;

    case LimitsChanged = 'limits_changed';
    case IncreaseScheduled = 'increase_scheduled';
    case IncreaseApplied = 'increase_applied';
    case CoolOff = 'cool_off';
    case SelfExclusion = 'self_exclusion';
}
