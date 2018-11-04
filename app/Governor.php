<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Governor extends Model
{

    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'coordinate_id', 'hired_for', 'level',
        'name', 'type', 'race', 'description',
        'cost_metal', 'cost_crystal', 'cost_gas',
        'energy_ph', 'dark_matter_cost',
        'attack_bonus', 'defence_bonus', 'shield_bonus', 'speed_bonus',

    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
     * Владелец губернатора
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function owner()
    {
        return $this->belongsToMany(User::class, 'coordinate_governors');
    }

    /**
     * Планета губернатора
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function location()
    {
        return $this->belongsTo(Planet::class);
    }




}


