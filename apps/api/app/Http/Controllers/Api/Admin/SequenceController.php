<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sequence;
use App\Models\SequenceStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Beheer van de opvolgcadans: welke stappen, in welke volgorde en met welke
 * wachttijd de agent een lead najaagt die niet opneemt.
 */
class SequenceController extends Controller
{
    /** @var list<string> */
    private const EDITABLE = ['delay_minutes', 'label', 'active', 'channel', 'action'];

    public function index(): JsonResponse
    {
        return response()->json([
            'sequences' => Sequence::with('steps')->get()->map(static fn (Sequence $sequence): array => [
                'key' => $sequence->key,
                'name' => $sequence->name,
                'description' => $sequence->description,
                'active' => $sequence->active,
                'steps' => $sequence->steps->map(static fn (SequenceStep $step): array => [
                    'id' => $step->id,
                    'position' => $step->position,
                    'channel' => $step->channel,
                    'action' => $step->action,
                    'delay_minutes' => $step->delay_minutes,
                    'label' => $step->label,
                    'active' => $step->active,
                ])->all(),
            ])->all(),
        ]);
    }

    public function updateStep(Request $request, int $id): JsonResponse
    {
        $unknown = array_diff(array_keys($request->all()), self::EDITABLE);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'payload' => 'Onbekende velden in het verzoek: '.implode(', ', $unknown).'.',
            ]);
        }

        $data = $request->validate([
            'delay_minutes' => ['sometimes', 'integer', 'min:0', 'max:43200'],
            'label' => ['sometimes', 'string', 'max:120'],
            'active' => ['sometimes', 'boolean'],
            'channel' => ['sometimes', 'in:call,email'],
            'action' => ['sometimes', 'string', 'max:60'],
        ]);

        $step = SequenceStep::findOrFail($id);
        $step->fill($data)->save();

        return response()->json([
            'step' => [
                'id' => $step->id,
                'position' => $step->position,
                'channel' => $step->channel,
                'action' => $step->action,
                'delay_minutes' => $step->delay_minutes,
                'label' => $step->label,
                'active' => $step->active,
            ],
        ]);
    }
}
