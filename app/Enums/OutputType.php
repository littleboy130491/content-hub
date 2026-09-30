<?php

namespace App\Enums;

enum OutputType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
}
