<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Comments extends Model
{

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'owner_id', 'coordinateX', 'coordinateY', 'orbit',
        'comment', 'description', 'share_with_alliance',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

}


