<?php

use Illuminate\Support\Str;

if (!function_exists('imagePath')) {

    /** image path for any model
     * @param object $object
     * @param string $notStdReally
     * @return string
     */
    function imagePath(object $object, string $notStdReally = null)
    {
        $name = $object->name;
        if (!empty($object->base_name))
            $name = $object->base_name;

        $class = class_basename($object);
        if (class_basename($object) == 'stdClass')
            $class = $notStdReally;

        $plural = Str::plural($class);

        return "images/" . Str::kebab($plural) . "/" . $object->race . "/" . Str::kebab($name) . ".png";
    }
}