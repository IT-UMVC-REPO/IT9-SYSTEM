<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case Reviewed = 'reviewed';
    case Dismissed = 'dismissed';
}
