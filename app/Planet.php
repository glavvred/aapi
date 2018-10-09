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
        'name', 'owner_id', 'type',
        'coordinateX', 'coordinateY', 'orbit',
        'metal', 'crystal', 'gas',
        'updated_at'
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
    public function user()

    {
        return $this->belongsTo(User::class);
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
}


