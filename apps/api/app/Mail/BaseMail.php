<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

abstract class BaseMail extends Mailable
{
    use Queueable;
}
