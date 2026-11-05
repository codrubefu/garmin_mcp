<?php

namespace App\Mcp\Tools;

use App\Garmin\GarminClient;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_profile_settings')]
#[Description('Get profile settings such as measurement units. Useful for interpreting other results.')]
class GetProfileSettings extends GarminTool
{
    protected function fetch(Request $request, GarminClient $garmin): mixed
    {
        return $garmin->get('/userprofile-service/userprofile/settings');
    }
}
