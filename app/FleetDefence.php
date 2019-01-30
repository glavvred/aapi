<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FleetDefence extends Model
{

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'fleet_id',
        'defence_id',
        'quantity'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

    public static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            // ... code here
        });

        self::created(function ($model) {
            // ... code here
        });

        self::updating(function ($model) {
            // ... code here
        });

        self::updated(function ($model) {
        });

        self::deleting(function ($model) {
            // ... code here
        });

        self::deleted(function ($model) {
            // ... code here
        });
    }

    /**
     * Получить все модели, обладающие флотами.
     */
    public function contains()
    {
        return $this->hasMany(Defence::class, 'id', 'defence_id');
    }

    /**
     * Parent fleet.
     */
    public function fleet()
    {
        return $this->belongsTo(Fleet::class, 'fleet_id', 'id')->get();
    }
}
