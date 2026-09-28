<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Open = 'open';
    case Billed = 'billed';
    case Paid = 'paid';
}
