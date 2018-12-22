<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FleetOrder extends Model
{

    public $timestamps = true;

    protected $table = 'orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'name',
        'is_combat',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
     * Флот
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo | Fleet
     */
    public function fleet()
    {
        return $this->belongsTo(Fleet::class)->first();
    }

}


