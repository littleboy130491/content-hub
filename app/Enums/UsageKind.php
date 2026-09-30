<?php

namespace App\Enums;

enum UsageKind: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case MediaReading = 'media_reading';
    case Scrape = 'scrape';
}
