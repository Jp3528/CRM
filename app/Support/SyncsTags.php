<?php

namespace App\Support;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait SyncsTags
{
    /**
     * Sincroniza tags existentes (ids) + crea los indicados en texto libre
     * separados por comas. Evita duplicados por slug.
     *
     * @param  array<int, int>  $tagIds
     */
    protected function syncTags(Model $model, array $tagIds, ?string $newTags): void
    {
        $ids = collect($tagIds)->map(fn ($id) => (int) $id)->filter()->unique();

        $names = collect(explode(',', (string) $newTags))
            ->map(fn (string $n) => trim($n))
            ->filter()
            ->unique()
            ->take(10);

        foreach ($names as $name) {
            $tag = Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
            $ids->push($tag->id);
        }

        $model->tags()->sync($ids->unique()->all());
    }
}
