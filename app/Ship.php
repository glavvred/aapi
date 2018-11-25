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
        return $this->belongsToMany('App\FleetShip','fleet_ships')
            ->withPivot('quantity');
    }

    /**
     * Перевод
     * @param string $language
     * @return Model|null|object|static
     */
    public function i18n(string $language)
    {
        $translated = $this
            ->hasOne(ShipLang::class, 'ship_name', 'name')
            ->where('language', $language)
            ->first();

        return $translated;
    }

}


