<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ship extends Model
{

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'type', 'race', 'description',
        'cost_metal', 'cost_crystal', 'cost_gas',
        'energy_ph', 'dark_matter_cost', 'cost_time',
        'attack', 'defence', 'shield', 'speed',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
     * Корабли на координатах
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function planet()
    {
        return $this->belongsToMany(Planet::class, 'planet_ships');
    }

}


