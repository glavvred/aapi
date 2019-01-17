<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PlanetDefence extends Model
{

    public $table = 'planet_defence';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'planet_id', 'defence_id', 'quantityQued',  'quantity',
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


