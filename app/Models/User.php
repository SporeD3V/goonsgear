<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'notify_cart_discounts',
        'notify_cart_low_stock',
        'delivery_phone',
        'delivery_country',
        'delivery_state',
        'delivery_city',
        'delivery_postal_code',
        'delivery_street_name',
        'delivery_street_number',
        'delivery_apartment_block',
        'delivery_entrance',
        'delivery_floor',
        'delivery_apartment_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'notify_cart_discounts' => 'boolean',
            'notify_cart_low_stock' => 'boolean',
        ];
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(UserCartItem::class);
    }

    public function sizeProfiles(): HasMany
    {
        return $this->hasMany(SizeProfile::class);
    }

    public function stockAlertSubscriptions(): HasMany
    {
        return $this->hasMany(StockAlertSubscription::class);
    }

    public function tagFollows(): HasMany
    {
        return $this->hasMany(TagFollow::class);
    }

    public function followedTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_follows')
            ->withPivot(['notify_new_drops', 'notify_discounts'])
            ->withTimestamps();
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class)
            ->withPivot(['usage_limit', 'used_count', 'is_active'])
            ->withTimestamps();
    }
}
