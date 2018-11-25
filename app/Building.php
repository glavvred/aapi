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
        'resources', 'requirements', 'upgrades',
        'metal_ph', 'crystal_ph', 'gas_ph', 'energy_ph',

    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];


    /**
     * Перевод
     * @param string $language
     * @return Model|null|object|static
     */
    public function i18n(string $language)
    {
        $translated = $this
            ->hasOne(BuildingLang::class, 'building_name', 'name')
            ->where('language', $language)
            ->first();

        return $translated;
    }

}


