<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Defence extends Model
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
        'resource', 'requirements', 'upgrades', 'properties',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
     * Корабли в составе флота
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function fleetShips()
    {
        return $this->belongsToMany(
            'App\FleetShip',
            'fleet_ships',
            'ship_id',
            'fleet_id'
        )
            ->withPivot('quantity');
    }

    /**
     * Перевод
     * @param string $language
     * @throws \Exception
     * @return Model|null|object|static
     */
    public function i18n(string $language)
    {
        $translated = $this
            ->hasMany(DefenceLang::class, 'defences_name', 'name')
            ->where('language', $language)
            ->first();

        if (empty($translated)) {
            throw  new \Exception('no translation found for defence_name:'.$this->name. ' and language:'.$language);
        }

        return $translated;
    }
}
