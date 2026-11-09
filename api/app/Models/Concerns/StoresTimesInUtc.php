<?php

namespace App\Models\Concerns;

/**
 * Times are stored in UTC. Eloquent formats a date as wall-clock time without an
 * offset, so a Carbon in another zone (e.g. a draw time typed in Europe/London)
 * would be stored an hour out in summer. Convert to UTC before formatting.
 *
 * The query builder doesn't go through this: pass it UTC values (now() is UTC).
 */
trait StoresTimesInUtc
{
    /**
     * @param  mixed  $value
     * @return string|null
     */
    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)->utc()->format($this->getDateFormat());
    }
}
