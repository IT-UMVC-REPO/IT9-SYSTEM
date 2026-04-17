<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case Gcash = 'gcash';
    case Maya = 'maya';
}
