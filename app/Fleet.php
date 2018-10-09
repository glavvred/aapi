<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Fleet extends Model
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
        'energy_ph', 'dark_matter_cost', 'cost_time'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

}


