<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BuildingLang extends Model
{

    /**
     * @var bool
     */
    public $timestamps = false;
    protected $table = 'buildings_lang';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'description',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];
}
