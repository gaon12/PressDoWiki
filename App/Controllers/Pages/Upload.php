<?php

namespace PressDo\App\Controllers\Pages;

use PressDo\App\Core\Controller;
use PressDo\App\Helpers\{Config, DefaultConfig, Languages, Namespaces};
use PressDo\App\Models\{Document, Files, Member};
use PressDo\App\Services\File\DuplicateFileException;
use PressDo\App\Services\File\UploadedFile;
use PressDo\App\Services\File\UploadedImage;
use PressDo\App\Services\File\UploadedImageInspector;
use PressDo\App\Services\File\UploadValidationException;
use PressDo\App\Services\Uploaders\ObjectStorageFactory;
use RuntimeException;

class Upload extends Controller
{
    /** @return array<string, mixed> */
    public function makeData(): array
    {
        $error = null;
        if (!DefaultConfig::get('wiki.file_upload')) {
            $error = 'err_file_upload_disabled';
        }

        $submittedDocument = $_POST['document'] ?? null;
        if (is_string($submittedDocument) && $submittedDocument !== '' && array_key_exists('file', $_FILES)) {
            [$namespace, $title] = self::parseTitle($submittedDocument);
            $image = null;
            try {
                $uploadedFile = UploadedFile::fromPhpFiles($_FILES['file']);
                $image = (new UploadedImageInspector())->inspect($uploadedFile, $submittedDocument);
            } catch (UploadValidationException $validationError) {
                $error = $validationError->messageKey;
            }

            // 이름공간 오류
            if ($namespace !== Namespaces::file()) {
                $error = 'err_invalid_file_namespace';
            }

            // 문서 중복
            if (Document::getUuid($namespace, $title)) {
                $error = 'err_document_exists';
            }

            // 정상 처리
            if ($error === null && $image instanceof UploadedImage) {
                $license = self::postString('license');
                $category = self::postString('category');
                $text = self::postString('text');
                $imageLicense = Languages::get('image_license');
                if (!is_string($imageLicense)) {
                    throw new RuntimeException('The image license namespace label is not configured.');
                }
                $content = '[include(' . Namespaces::template() . ':' . $imageLicense . '/' . $license . ")]\n"
                    . '[[' . Namespaces::category() . ':' . Namespaces::file() . '/' . $category . "]]\n" . $text;

                $sessionUuid = self::sessionString($this->session, 'uuid');
                $member = !empty($this->session['member']) ? $sessionUuid : null;
                $ip = $member === null
                    ? ($sessionUuid ?? Member::getIpUuid(self::sessionString($this->session, 'ip') ?? ''))
                    : null;
                if ($member === null && $sessionUuid === null) {
                    $this->session['uuid'] = $ip;
                }

                $summary = self::postString('summary');
                $historyMessage = Languages::get('history', 'uploaded_file');
                $comment = $summary !== ''
                    ? $summary
                    : sprintf(is_string($historyMessage) ? $historyMessage : 'Uploaded file %s', $image->file->originalName);
                $storageType = Config::get('storage.type');
                if (!is_string($storageType)) {
                    throw new RuntimeException('The object storage type is not configured.');
                }
                try {
                    Files::uploadDocument(
                        ObjectStorageFactory::create($storageType),
                        $image->file->temporaryPath,
                        $image->objectKey(),
                        $namespace,
                        $title,
                        $content,
                        $comment,
                        $member,
                        $ip,
                        $image->sha256,
                        $image->width,
                        $image->height,
                    );

                    Header('Location: /w/' . $submittedDocument);
                    exit;
                } catch (DuplicateFileException) {
                    $this->error = self::uploadError('err_duplicate_file');
                }
            } else {
                $this->error = self::uploadError($error ?? 'err_invalid_file');
            }
        }

        $dataset = Document::getLicensesAndCategories();
        $imageLicense = Languages::get('image_license');
        if (!is_string($imageLicense)) {
            throw new RuntimeException('The image license namespace label is not configured.');
        }
        $licenses = [];
        foreach ($dataset['License'] as $license) {
            $licenses[] = substr($license['title'], strlen($imageLicense . '/'));
        }
        $categories = [];
        foreach ($dataset['Category'] as $category) {
            $categories[] = substr($category['title'], strlen(Namespaces::file() . '/'));
        }
        $pageLabels = Languages::get('page');
        $pageTitle = is_array($pageLabels) && is_string($pageLabels['Upload'] ?? null)
            ? $pageLabels['Upload']
            : 'Upload';
        $page = [
            'view_name' => 'Upload',
            'title' => $pageTitle,
            'data' => [
                'Licenses' => $licenses,
                'Categories' => $categories,
            ],
            'menus' => [],
            'customData' => [],
        ];

        return $page;
    }

    private static function postString(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /** @param array<mixed> $session */
    private static function sessionString(array $session, string $key): ?string
    {
        $value = $session[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array{code: string, message: string, errbox: true} */
    private static function uploadError(string $code): array
    {
        $message = Languages::get('msg', $code);

        return [
            'code' => $code,
            'message' => is_string($message) ? $message : $code,
            'errbox' => true,
        ];
    }
}
