<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Quote;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

class QuotePdfRenderer
{
    public function render(Quote $quote): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml(View::make('pdf.quote', [
            'quote' => $quote,
            'lead' => $quote->lead,
            'items' => $quote->items,
            'company' => config('agent.company'),
        ])->render());
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
