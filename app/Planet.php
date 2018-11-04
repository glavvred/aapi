<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Planet extends Model
{

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'owner_id', 'type', 'classType',
        'slots', 'temperature', 'diameter', 'density',
        'coordinateX', 'coordinateY', 'orbit',
        'metal', 'crystal', 'gas',
        'created_at', 'updated_at'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
     * Вернем юзера, владеющего планетой
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    /**
     * Строения на планете
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function buildings()
    {
        return $this->belongsToMany(Building::class, 'planet_building')
            ->withPivot('level', 'startTime', 'timeToBuild', 'updated_at', 'destroying');
    }

    /**
     * Корабли на планете
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function ships()
    {
        return $this->belongsToMany(Ship::class, 'planet_ships')
            ->withPivot('quantity');
    }

    /**
     * Флоты на планете
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function fleets()
    {
        return $this->hasMany(Fleet::class);
    }

    /**
     * Губернаторы на планете
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function governors()
    {
        return $this->belongsToMany(Governor::class, 'coordinate_governors', 'coordinate_id');
    }

}


