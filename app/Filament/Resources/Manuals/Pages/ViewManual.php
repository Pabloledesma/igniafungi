<?php

namespace App\Filament\Resources\Manuals\Pages;

use App\Filament\Resources\Manuals\ManualResource;
use Filament\Resources\Pages\ViewRecord;

class ViewManual extends ViewRecord
{
    protected static string $resource = ManualResource::class;

    protected string $view = 'filament.resources.manuals.pages.view-manual';
}
