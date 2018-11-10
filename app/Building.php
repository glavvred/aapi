<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Building extends Model
{

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'type', 'race', 'description',
        'cost_metal', 'cost_crystal', 'cost_gas',
        'dark_matter_cost', 'cost_time',
        'resources',
        'metal_ph', 'crystal_ph', 'gas_ph', 'energy_ph',

    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];


}


