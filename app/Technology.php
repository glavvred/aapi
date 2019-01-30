<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Technology extends Model
{

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'type', 'race', 'description',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
     * Технологи юзера
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function owner()
    {
        return $this->belongsToMany(User::class, 'user_technologies')
            ->withPivot(["level", "planet_id", "startTime", "timeToBuild"])
            ->withTimestamps();
    }

    /**
     * Перевод
     * @param string $language
     * @throws \Exception
     * @return mixed
     */
    public function i18n(string $language)
    {
        $translated = $this
            ->hasMany(TechnologyLang::class, 'technology_name', 'name')
            ->where('language', $language)
            ->first();

        if (empty($translated)) {
            throw  new \Exception('no translation found for technology_name :'.$this->name. ' and language: '.$language);
        }
        return $translated;
    }
}
