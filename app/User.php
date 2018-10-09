<?php

namespace App;

use Illuminate\Auth\Authenticatable;
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
        'name', 'email', 'password','userimage'
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
     * Строение есть у многих пользователей.
     */
    public function buildings()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Флоты есть у многих пользователей.
     */
    public function fleets()
    {
        return $this->belongsToMany(User::class);
    }



}




