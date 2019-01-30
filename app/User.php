<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public $scope;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'userimage', 'race', 'alliance_id', 'language'
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'api_key',
    ];

    public function planets()
    {
        return $this->hasMany(Planet::class, 'owner_id');
    }

    /**
     * Технологии юзера
     * @return BelongsToMany
     */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class, 'user_technologies', 'owner_id')
            ->withPivot(['level',
                'startTime',
                'timeToBuild',
                'planet_id',
            ])
            ->withTimestamps();

//       todo: ->using(TechnologyAtUser::class);
    }

    /**
     * Флоты есть у многих пользователей.
     */
    public function fleets()
    {
        return $this->hasMany(Fleet::class, 'owner_id');
    }

    /**
     * Пользователь может состоять только в одном альянсе.
     */
    public function alliance()
    {
        return $this->hasOne(Alliance::class, 'id', 'alliance_id');
    }

    /**
     * Пользователь может говорить только на одном языке.
     */
    public function language()
    {
        return $this->language;
    }
}
