<?php

declare(strict_types=1);

namespace PressDo\App\Controllers\Pages\api;

use InvalidArgumentException;
use LengthException;
use PressDo\App\Core\Controller;
use PressDo\App\Core\Response;
use PressDo\App\Http\PreviewInput;

final class Preview extends Controller
{
    public function makeData(): never
    {
        if (!$this->request->isMethod('POST')) {
            Response::methodNotAllowed('POST');
        }

        try {
            $input = PreviewInput::fromRequest($this->request);
        } catch (LengthException $error) {
            Response::text($error->getMessage(), 413);
        } catch (InvalidArgumentException $error) {
            Response::text($error->getMessage(), 422);
        }

        $content = self::readSyntax($input->text, [
            'title' => $input->title,
            'thread' => false,
        ]);

        Response::html($content->html);
    }
}
