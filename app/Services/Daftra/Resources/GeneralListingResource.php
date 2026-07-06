<?php

namespace App\Services\Daftra\Resources;

class GeneralListingResource extends Resource
{
    protected function path(): string
    {
        return '/general_listing';
    }

    protected function entityKey(): string
    {
        return 'Listing';
    }
}
