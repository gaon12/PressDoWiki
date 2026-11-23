<?php

namespace PressDo\App\Controllers\Pages\Member;

use PressDo\App\Core\{Controller, Response};
use PressDo\App\Models\{Document, Star};

class Unstar extends Controller
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

        Star::unstar($document, $this->session['uuid']);
        Response::redirect('/member/starred_documents', 303);
    }
}
