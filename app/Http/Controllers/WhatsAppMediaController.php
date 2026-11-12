<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMediaAsset;
use App\Services\WhatsApp\WhatsAppMediaLibrary;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WhatsAppMediaController extends Controller
{
    public function show(WhatsAppMediaAsset $asset, WhatsAppMediaLibrary $library): SymfonyResponse
    {
        abort_unless($asset->fileExists(), 404);

        return $library->stream($asset);
    }
}
