<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CallOutcome;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Call;
use App\Models\Lead;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Funnel- en prestatiecijfers voor het dashboard.
 *
 * De funnel meet "heeft deze stap bereikt", niet "staat nu in deze stap":
 * een lead die al een afspraak heeft, telt ook mee in alle stappen ervoor.
 * Anders zou de trechter leeglopen zodra leads doorstromen.
 */
class AnalyticsController extends Controller
{
    /** Statussen die aantonen dat een stap gepasseerd is. */
    private const REACHED = [
        'new' => ['new', 'enriched', 'calling', 'qualified', 'quoted', 'follow_up', 'appointment_scheduled', 'won', 'lost', 'unreachable', 'do_not_contact'],
        'enriched' => ['enriched', 'calling', 'qualified', 'quoted', 'follow_up', 'appointment_scheduled', 'won', 'lost', 'unreachable', 'do_not_contact'],
        'calling' => ['calling', 'qualified', 'quoted', 'follow_up', 'appointment_scheduled', 'won', 'lost', 'unreachable', 'do_not_contact'],
        'qualified' => ['qualified', 'quoted', 'follow_up', 'appointment_scheduled', 'won'],
        'quoted' => ['quoted', 'follow_up', 'appointment_scheduled', 'won'],
        'follow_up' => ['follow_up', 'appointment_scheduled', 'won'],
        'appointment_scheduled' => ['appointment_scheduled', 'won'],
        'won' => ['won'],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $total = Lead::whereBetween('created_at', [$from, $to])->count();

        $funnel = [];
        $previous = null;

        foreach (LeadStatus::funnel() as $status) {
            $count = Lead::whereBetween('created_at', [$from, $to])
                ->whereIn('status', self::REACHED[$status->value])
                ->count();

            $funnel[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $count,
                'share_of_total' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
                'step_conversion' => $previous !== null && $previous > 0 ? round($count / $previous * 100, 1) : null,
                'dropped' => $previous !== null ? max(0, $previous - $count) : 0,
            ];

            $previous = $count;
        }

        $callsQuery = Call::whereBetween('created_at', [$from, $to]);
        $callTotal = (clone $callsQuery)->whereNotNull('outcome')->count();
        $answered = (clone $callsQuery)->whereIn('outcome', array_map(
            static fn (CallOutcome $outcome): string => $outcome->value,
            array_filter(CallOutcome::cases(), static fn (CallOutcome $outcome): bool => $outcome->reachedLead()),
        ))->count();

        $quotesSent = Quote::whereBetween('created_at', [$from, $to])->whereNotNull('sent_at')->count();
        $quotesAccepted = Quote::whereBetween('created_at', [$from, $to])->where('status', 'accepted')->count();
        $quoteValue = (int) Quote::whereBetween('created_at', [$from, $to])->where('status', 'accepted')->sum('total_cents');

        return response()->json([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [
                'leads' => $total,
                'calls' => $callTotal,
                'calls_answered' => $answered,
                'answer_rate' => $callTotal > 0 ? round($answered / $callTotal * 100, 1) : 0.0,
                'quotes_sent' => $quotesSent,
                'quotes_accepted' => $quotesAccepted,
                'quote_acceptance_rate' => $quotesSent > 0 ? round($quotesAccepted / $quotesSent * 100, 1) : 0.0,
                'appointments' => Appointment::whereBetween('created_at', [$from, $to])->count(),
                'booked_value_cents' => $quoteValue,
            ],
            'funnel' => $funnel,
            'by_source' => Lead::whereBetween('created_at', [$from, $to])
                ->selectRaw('source, count(*) as aantal')
                ->groupBy('source')
                ->pluck('aantal', 'source'),
            'call_outcomes' => Call::whereBetween('created_at', [$from, $to])
                ->whereNotNull('outcome')
                ->selectRaw('outcome, count(*) as aantal')
                ->groupBy('outcome')
                ->pluck('aantal', 'outcome'),
            'lost_reasons' => Lead::whereBetween('created_at', [$from, $to])
                ->whereNotNull('lost_reason')
                ->selectRaw('lost_reason, count(*) as aantal')
                ->groupBy('lost_reason')
                ->orderByDesc('aantal')
                ->limit(10)
                ->pluck('aantal', 'lost_reason'),
        ]);
    }
}
