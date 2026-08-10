<?php

namespace PressDo\App\Controllers\Pages\Member;

use PressDo\App\Core\{Controller, Response};
use PressDo\App\Models\Member;

class Logout extends Controller
{
    public function makeData(): void
    {
        if ($this->request->hasCookie('szczecin')) {
            Member::deleteCookie($this->session['uuid'], 'szczecin', $this->request->cookieString('szczecin'));
            setcookie('szczecin', '', self::getCookieOptions(-3600));
        }
        unset($this->session);
        session_destroy();

        Response::redirect($this->request->queryString('redirect', '/'));
    }
}
