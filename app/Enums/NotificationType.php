<?php

namespace App\Enums;

enum NotificationType: string
{
    case NewProduct = 'new_product';
    case OrderUpdate = 'order_update';
    case Message = 'message';
    case System = 'system';
}
