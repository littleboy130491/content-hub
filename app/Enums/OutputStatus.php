<?php

namespace App\Enums;

enum OutputStatus: string
{
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isRunning(): bool
    {
        return in_array($this, [self::Pending, self::Processing]);
    }
}
