<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class Category extends Model
{
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
