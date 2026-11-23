<?php

namespace PressDo\App\Controllers\Pages\Member;

use PressDo\App\Core\{Controller, Response};
use PressDo\App\Http\Security\SessionLifecycle;
use PressDo\App\Http\Security\SessionRegistry;
use PressDo\App\Models\Member;

class Logout extends Controller
{
    public function makeData(): never
    {
        if (!$this->request->isMethod('POST')) {
            Response::methodNotAllowed('POST');
        }

        $uuid = $this->session['uuid'] ?? null;
        $id = $this->session['device_session'] ?? null;
        if (is_string($uuid) && is_string($id)) {
            Member::revokeLoginSession($uuid, $id);
        }

        foreach ([SessionRegistry::REMEMBER, SessionRegistry::TRUST] as $name) {
            setcookie($name, '', self::getCookieOptions(-3600));
        }

        $this->session = [];
        SessionLifecycle::destroy();

        Response::redirect($this->request->queryString('redirect', '/'), 303);
    }
}
