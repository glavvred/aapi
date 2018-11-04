<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Captain extends Model
{

    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'type', 'race', 'description',
        'cost_metal', 'cost_crystal', 'cost_gas',
        'energy_ph', 'dark_matter_cost', 'cost_time',
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
     * Владелец капитана
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Флот капитана
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function fleet()
    {
        return $this->belongsTo(Fleet::class);
    }




}


