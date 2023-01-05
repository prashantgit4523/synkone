<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Str;

class CustomCleanHtml implements CastsAttributes
{
    /**
     * Clean the HTML when casting the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return array
     */
    public function get($model, $key, $value, $attributes)
    {
        return htmlspecialchars_decode($value);
    }

    /**
     * Prepare the given value for storage by cleaning the HTML.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  array  $value
     * @param  array  $attributes
     * @return string
     */
    public function set($model, $key, $value, $attributes)
    {
        if (!Str::contains(strtolower($value), 'script') && Str::contains($value, '<')) {
            return $value;
        } else {
            return clean($value);
        }
    }
}
