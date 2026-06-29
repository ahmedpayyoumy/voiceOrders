<?php

namespace App\Services\Daftra\Interpreter;

enum IntentType: string
{
    case List = 'list';
    case Get = 'get';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Count = 'count';
    case Unknown = 'unknown';
}
