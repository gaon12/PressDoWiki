<?php

declare(strict_types=1);

namespace PressDo\App\Admin\Settings;

enum SettingType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Boolean = 'boolean';
    case Url = 'url';
    case Timezone = 'timezone';
    case Hostname = 'hostname';
    case Identifier = 'identifier';
    case Choice = 'choice';
}
