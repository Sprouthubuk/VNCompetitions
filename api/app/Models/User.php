<?php

namespace App\Models;

use App\Models\Concerns\StoresTimesInUtc;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $date_of_birth
 * @property string|null $phone
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $county
 * @property string|null $postcode
 * @property string $country
 * @property CarbonImmutable|null $age_confirmed_at
 * @property bool $marketing_email
 * @property bool $marketing_sms
 * @property bool $is_admin
 */
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use StoresTimesInUtc;
    use Notifiable;

    /**
     * Customer-editable fields. is_admin is deliberately not mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'date_of_birth',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'county',
        'postcode',
        'country',
        'marketing_email',
        'marketing_sms',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'date_of_birth' => 'immutable_date',
            'age_confirmed_at' => 'immutable_datetime',
            'marketing_email' => 'boolean',
            'marketing_sms' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasMany<WalletTransaction, $this> */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /** @return HasOne<ResponsibleLimit, $this> */
    public function responsibleLimit(): HasOne
    {
        return $this->hasOne(ResponsibleLimit::class);
    }

    /** @return HasMany<LimitEvent, $this> */
    public function limitEvents(): HasMany
    {
        return $this->hasMany(LimitEvent::class);
    }

    /** @return HasMany<Winner, $this> */
    public function wins(): HasMany
    {
        return $this->hasMany(Winner::class);
    }
}
