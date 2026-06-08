<?php

namespace App\Livewire\Concerns;

trait InteractsWithSearchableSelects
{
    public function selectSearchableOption(string $valueProperty, string $searchProperty, int|string $value, string $label): void
    {
        data_set($this, $valueProperty, (string) $value);
        data_set($this, $searchProperty, $label);
    }

    public function clearSearchableOption(string $valueProperty, string $searchProperty): void
    {
        data_set($this, $valueProperty, '');
        data_set($this, $searchProperty, '');
    }
}
