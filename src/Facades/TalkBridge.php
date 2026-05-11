<?php
namespace RahatulRabbi\TalkBridge\Facades;

use Illuminate\Support\Facades\Facade;

class TalkBridge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \RahatulRabbi\TalkBridge\Services\ChatService::class;
    }
}
