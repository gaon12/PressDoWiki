<?php

namespace PressDo\App\Controllers\Pages\Member;

use PressDo\App\Core\{Controller, Response};
use PressDo\App\Models\{Document, Star as StarModel};
use PressDo\App\Controllers\ACL as WikiACL;
use PressDo\App\Http\WikiUrl;

class Star extends Controller
{
    public function makeData(): never
    {
        if (!$this->request->isMethod('POST')) {
            Response::methodNotAllowed('POST');
        }
        if (empty($this->session['member'])) {
            Response::redirect('/member/login');
        }

        $title = $this->uri_data->title;
        [$namespace, $documentTitle] = self::parseTitle($title);
        $document = Document::getUuid($namespace, $documentTitle);
        if ($document === false) {
            Response::notFound();
        }
        $error = [];
        (new WikiACL($namespace, $documentTitle, $document, $this->session, $error))->check('read');
        if ($error !== []) {
            Response::text('Forbidden', 403);
        }

        StarModel::star($document, $this->session['uuid']);
        Response::redirect(WikiUrl::document($title), 303);
    }
}
