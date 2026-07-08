<?php

namespace App\Services\Daftra\Resources;

class GeneralListingResource extends Resource
{
    protected function path(): string
    {
        return '/general_listing';
    }

    public function entityKey(): string
    {
        return 'Listing';
    }
}
