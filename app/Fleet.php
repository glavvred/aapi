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
     * Маршруты
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function routes()
    {
        return $this->hasMany(Route::class, 'fleet_id', 'id')->get();
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


    /**
     * Приказ флота
     * @return Model|null|object|static
     */
    public function order()
    {
        $order = $this->hasOne(FleetOrder::class, 'id', 'order_type')->first();
        if (!empty($order))
            return $order;
        else
            return new FleetOrder(['name' => 'empty', 'type' => 0]);
    }

}


