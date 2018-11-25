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

    /**
     * Получить все модели, обладающие флотами.
     */
    public function contains()
    {
        return $this->hasMany(Ship::class, 'id', 'ship_id');
    }
}


