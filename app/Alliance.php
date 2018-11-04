<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Alliance extends Model
{

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'type', 'description',
        'parent_id', 'requirements', 'leader_id',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
     * Parent alliance.
     */
    public function parent_id()
    {
        return $this->belongsTo('App\Alliance');
    }

}


