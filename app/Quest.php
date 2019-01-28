<?php

namespace App;

class Quest
{

    public $timestamps = true;
    public $daily;
    public $storyline;
    public $tutorial;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */


    protected $fillable = [
        'name', 'type', 'race', 'is_hidden', 'parent_id',
        'requirements', 'objectives',
        'reward_resources', 'reward_items', 'reward_units',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
     * Предыдущий квест по цепочке
     * @return mixed
     */
    public function parent()
    {
        return $this->belongsTo(Quest::class, 'parent_id');
    }

    /**
     * Следующий квест по цепочке
     * @return mixed
     */
    public function children()
    {
        return $this->hasMany(Quest::class, 'parent_id');
    }

}


