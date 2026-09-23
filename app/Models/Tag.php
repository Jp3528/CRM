<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'description'];

    /** @return MorphToMany<Company, $this> */
    public function companies(): MorphToMany
    {
        return $this->morphedByMany(Company::class, 'taggable');
    }

    /** @return MorphToMany<Contact, $this> */
    public function contacts(): MorphToMany
    {
        return $this->morphedByMany(Contact::class, 'taggable');
    }

    /** @return MorphToMany<Lead, $this> */
    public function leads(): MorphToMany
    {
        return $this->morphedByMany(Lead::class, 'taggable');
    }

    /** @return MorphToMany<Opportunity, $this> */
    public function opportunities(): MorphToMany
    {
        return $this->morphedByMany(Opportunity::class, 'taggable');
    }
}
