<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Vendor = 'vendor';
    case Rider = 'rider';
    case Admin = 'admin';
}
