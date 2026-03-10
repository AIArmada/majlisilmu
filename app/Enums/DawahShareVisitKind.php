<?php

namespace App\Enums;

enum DawahShareVisitKind: string
{
    case Landing = 'landing';
    case Return = 'return';
    case Navigated = 'navigated';
}
