<?php

namespace PressDo\App\Controllers\Pages\Member;

use PressDo\App\Core\{Controller, Response};
use PressDo\App\Http\Security\SessionLifecycle;
use PressDo\App\Models\Member;

class Logout extends Controller
{
    public function makeData(): never
    {
        if (!$this->request->isMethod('POST')) {
            Response::methodNotAllowed('POST');
        }

        $uuid = $this->session['uuid'] ?? null;
        foreach (['szczecin', 'podgorica'] as $name) {
            if (!$this->request->hasCookie($name)) {
                continue;
            }

            if (is_string($uuid)) {
                Member::deleteCookie($uuid, $name, $this->request->cookieString($name));
            }
            setcookie($name, '', self::getCookieOptions(-3600));
        }

        $this->session = [];
        SessionLifecycle::destroy();

        Response::redirect($this->request->queryString('redirect', '/'), 303);
    }
}
