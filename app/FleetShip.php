<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FleetShip extends Model
{

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'fleet_id',
        'ship_id',
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

            $fleet = $model->fleet()->first();

            $fleet->overall_capacity = app('App\Http\Controllers\ShipController')->recalculateCapacity($fleet->id);
            $fleet->overall_speed = app('App\Http\Controllers\ShipController')->recalculateSpeed($fleet->id);
            $fleet->save();
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
        return $this->hasMany(Ship::class, 'id', 'ship_id');
    }

    /**
     * Parent fleet.
     */
    public function fleet()
    {
        return $this->belongsTo(Fleet::class, 'fleet_id', 'id')->get();
    }


}


