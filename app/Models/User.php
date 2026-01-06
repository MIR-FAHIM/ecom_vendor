<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
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
        'mobile',          // Newly added
        'address',         // Newly added
        'optional_phone',  // Newly added
        'fcm_token',       // Newly added
        'is_banned',       // Newly added
        'role',            // Newly added
        'status',          // Newly added
        'zone',            // Newly added
        'district',        // Newly added
        'area',            // Newly added
        'lat',             // Newly added
        'lon',             // Newly added
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
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_banned' => 'boolean',  // Cast 'is_banned' as boolean
            'lat' => 'float',          // Cast 'lat' as float
            'lon' => 'float',          // Cast 'lon' as float
        ];
    }

    /**
     * Mutators for setting attributes
     *
     * @param string $value
     * @return void
     */
    public function setRoleAttribute($value)
    {
        $this->attributes['role'] = strtolower($value); // Make sure role is always lowercase
    }

    /**
     * Accessor for the 'status' attribute.
     * You can customize how the 'status' value is represented.
     *
     * @return string
     */
    public function getStatusAttribute($value)
    {
        return ucfirst($value); // Capitalize status (active/inactive)
    }
}
