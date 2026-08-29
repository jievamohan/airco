<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Services\LeadIntake;
use Illuminate\Http\JsonResponse;

class LeadIntakeController extends Controller
{
    /**
     * Neemt een aanvraag van het websiteformulier aan.
     *
     * Het antwoord bevat bewust niets meer dan een bevestiging: geen id's,
     * geen statussen, niets waarmee een buitenstaander het CRM kan aftasten.
     */
    public function store(StoreLeadRequest $request, LeadIntake $intake): JsonResponse
    {
        $intake->capture($request->leadAttributes(), $request->leadSource());

        return response()->json(['ok' => true], 202);
    }
}
