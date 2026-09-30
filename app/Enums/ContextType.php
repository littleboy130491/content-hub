<?php

namespace App\Enums;

enum ContextType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Web = 'web';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Image => 'Image',
            self::Video => 'Video',
            self::Web => 'Web page',
        };
    }

    /**
     * The modality a model must read to receive this item without conversion.
     */
    public function modality(): string
    {
        return match ($this) {
            self::Text, self::Web => 'text',
            self::Image => 'image',
            self::Video => 'video',
        };
    }
}
