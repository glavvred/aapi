<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Fleet extends Model
{

    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'owner_id',
        'origin_id',
        'coordinate_id',
        'destination_id',
        'captainId',
        'orderType',
        'overall_capacity',
        'overall_speed',
        'metal',
        'crystal',
        'gas',

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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo | User
     */
    public function owner()
    {
        return $this->belongsTo(User::class)->first();
    }


    /**
     * Текущая координата
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function coordinate()
    {
        return $this->belongsTo(Planet::class, 'coordinate_id', 'id');
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


