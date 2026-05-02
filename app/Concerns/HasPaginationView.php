<?php

namespace App\Concerns;

trait HasPaginationView
{
    public function paginationView(): string
    {
        return 'layouts.app.livewire-paginate';
    }
}
