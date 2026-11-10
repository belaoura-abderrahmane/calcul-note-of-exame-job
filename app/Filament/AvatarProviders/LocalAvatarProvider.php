<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/** صورة رمزية محلية (تعمل دون اتصال بالإنترنت) */
class LocalAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = Filament::getNameForDefaultAvatar($record);
        $initial = htmlspecialchars(mb_substr(trim($name), 0, 1) ?: '?');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#059669"/>'
            .'<text x="32" y="42" font-size="30" font-family="Tahoma,Arial" text-anchor="middle" fill="#fff">'.$initial.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
