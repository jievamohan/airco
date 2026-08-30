<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * De ontdubbeling sleutelde op e-mail plus telefoon, en dat ging twee kanten
 * op mis: dezelfde woning met twee mailadressen werd twee leads, en twee
 * verschillende klussen van dezelfde persoon werden er één.
 *
 * De regel staat hier bewust uitgeschreven in plaats van dat de service wordt
 * aangeroepen: een migratie hoort te blijven doen wat hij deed op de dag dat
 * hij draaide, ook als de service later verandert.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('leads')->select('id', 'address', 'postcode', 'email', 'phone')->cursor() as $lead) {
            DB::table('leads')->where('id', $lead->id)->update([
                'dedupe_hash' => $this->hash($lead),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('leads')->select('id', 'email', 'phone')->cursor() as $lead) {
            $email = strtolower(trim((string) $lead->email));
            $phone = (string) preg_replace('/\D/', '', (string) $lead->phone);

            DB::table('leads')->where('id', $lead->id)->update([
                'dedupe_hash' => hash('sha256', $email.'|'.$phone),
            ]);
        }
    }

    private function hash(object $lead): string
    {
        $postcode = strtoupper((string) preg_replace('/\s+/', '', (string) ($lead->postcode ?? '')));

        if (preg_match('/^\d{4}[A-Z]{2}$/', $postcode) === 1
            && preg_match('/\d+/', (string) ($lead->address ?? ''), $treffer) === 1) {
            return hash('sha256', 'adres|'.$postcode.'|'.$treffer[0]);
        }

        $email = strtolower(trim((string) ($lead->email ?? '')));
        $phone = (string) preg_replace('/\D/', '', (string) ($lead->phone ?? ''));

        return hash('sha256', 'contact|'.$email.'|'.$phone);
    }
};
