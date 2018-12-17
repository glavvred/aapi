<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{

    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'parent_id',
        'owner_id',
        'fleet_id',
        'coordinate_id',
        'destination_id',
        'order',

    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
     * Флот маршрута
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function fleet()
    {
        return $this->belongsTo(Fleet::class)->first();
    }

    /**
     * Координата входа
     * @return \Illuminate\Database\Eloquent\Relations\hasOne
     */
    public function origin()
    {
        return $this->hasOne(Planet::class, 'id', 'coordinate_id')->first();
    }

    /**
     * Координата выхода
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function destination()
    {
        return $this->belongsTo(Planet::class)->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(Route::class,'parent_id')->where('parent_id',0)->with('parent');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function children()
    {
        return $this->hasOne(Route::class,'parent_id')->with('children');
    }

}


