<?php

namespace App\Enums;

enum VideoCallStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Ended = 'ended';
    case Declined = 'declined';
}
