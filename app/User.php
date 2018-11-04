<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password','userimage', 'race', 'alliance_id'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'api_key',
    ];

    public $scope;

    public function planets()

    {
        return $this->hasMany(Planet::class,'owner_id');
    }

    /**
     * Технологии юзера
     * @return BelongsToMany
     */
    public function technologies() : BelongsToMany
    {
        return $this->belongsToMany(Technology::class,'user_technologies', 'owner_id')
            ->withPivot(['level',
                'startTime',
                'timeToBuild',
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
        return $this->hasOne('App\Alliance');
    }


}




