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
        'owner_id',
        'originId',
        'coordinateId',
        'captainId'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
     * Владелец флота
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Текущая координата
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function coordinate()
    {
        return $this->belongsToMany(Planet::class, 'fleets', 'id', 'coordinate_id')
            ->withPivot('quantity');
    }

    /**
     * Маршрут
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * Капитан
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function captain()
    {
        return $this->belongsTo(Captain::class);
    }

    /**
     * Корабли в флоте
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function ships()
    {
        return $this->hasMany(FleetShip::class, 'fleet_id');
    }


}


