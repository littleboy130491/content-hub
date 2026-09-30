<?php

namespace App\Enums;

/**
 * How a project or chat system prompt combines with the one above it.
 */
enum PromptMode: string
{
    case Append = 'append';
    case Replace = 'replace';
}
