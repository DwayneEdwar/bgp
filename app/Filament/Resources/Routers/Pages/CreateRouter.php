<?php

namespace App\Filament\Resources\Routers\Pages;

use App\Filament\Resources\Routers\RouterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRouter extends CreateRecord
{
    protected static string $resource = RouterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'unknown';
        $data['last_connected_at'] = null;

        return $data;
    }
}
