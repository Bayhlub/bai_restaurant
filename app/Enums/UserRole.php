<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Kitchen = 'kitchen';
    case Cashier = 'cashier';

    public function label(): string
    {
        return __('roles.'.$this->value);
    }
}
