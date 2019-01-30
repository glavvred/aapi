<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
     * @return mixed
     */
    public function requires()
    {
        return $this->requirements;
    }

    public function planet()
    {
        DB::table('planet_building')
            ->where('building_id', $this->id)
            ->first();
    }

    /**
     * Перевод
     * @throws \Exception
     * @param string $language
     * @return Model|null|object|static
     */
    public function i18n(string $language)
    {
        $translated = $this
            ->hasOne(BuildingLang::class, 'building_name', 'name')
            ->where('language', $language)
            ->first();

        if (empty($translated)) {
            throw  new \Exception('no translation found for building_name:' . $this->name . ' and language:' . $language);
        }

        return $translated;
    }
}
