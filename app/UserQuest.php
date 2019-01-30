<?php

namespace App;

class UserQuest
{

    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */


    protected $fillable = [
        'user_id', 'quest_id', 'claimed', 'started_at', 'completed_at'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];
}
