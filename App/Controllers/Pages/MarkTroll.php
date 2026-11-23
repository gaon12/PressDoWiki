<?php

namespace PressDo\App\Controllers\Pages;

use PressDo\App\Controllers\ACL as WikiACL;
use PressDo\App\Core\{Controller, Response};
use PressDo\App\Models\{Document, History};

class MarkTroll extends Controller
{
    public function makeData(): never
    {
        if (!$this->request->isMethod('POST')) {
            Response::methodNotAllowed('POST');
        }
        if (empty($this->session['member'])) {
            Response::text('Forbidden', 403);
        }

        $title = $this->uri_data->title;
        [$namespace, $documentTitle] = self::parseTitle($title);
        $document = Document::getUuid($namespace, $documentTitle);
        $revision = $this->request->postString('uuid');
        if ($document === false || preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/i', $revision) !== 1) {
            Response::notFound();
        }

        $error = [];
        $acl = new WikiACL($namespace, $documentTitle, $document, $this->session, $error);
        $acl->check('read');
        if ($error !== [] || !in_array('mark_troll_revision', $acl->perms, true)) {
            Response::text('Forbidden', 403);
        }
        $acl->check('edit');
        if ($error !== []) {
            Response::text('Forbidden', 403);
        }

        History::markTroll($document, $revision, $this->session['uuid']);
        Response::redirect('/history/' . rawurlencode($title), 303);
    }
}
