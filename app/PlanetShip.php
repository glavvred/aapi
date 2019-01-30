<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PlanetShip extends Model
{

    public $table = 'planet_ship';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'planet_id', 'ship_id', 'quantityQued',  'quantity',
        'startTime', 'timeToBuildOne', 'passedFromLastOne',
        'created_at', 'updated_at'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];
}
