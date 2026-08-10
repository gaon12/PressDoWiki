<?php

declare(strict_types=1);

namespace PressDo\App\Controllers\Pages\admin;

use PressDo\App\Admin\Settings\SettingDefinition;
use PressDo\App\Admin\Settings\SettingsCatalog;
use PressDo\App\Admin\Settings\SettingsValidationException;
use PressDo\App\Core\Controller;
use PressDo\App\Helpers\Config as RuntimeConfig;
use PressDo\App\Helpers\Languages;
use PressDo\App\Models\ACL;

final class Config extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function makeData(): array
    {
        if (!$this->canManageSettings()) {
            return [
                'view_name' => 'error',
                'title' => Languages::get('page', 'error'),
                'data' => ['code' => 'no_permission'],
            ];
        }

        $catalog = new SettingsCatalog();
        $values = RuntimeConfig::all();
        $errors = [];
        $saved = false;
        $token = $this->csrfToken();

        if ($this->request->hasPost('settings')) {
            $submittedToken = $this->request->postString('token');

            if (!hash_equals($token, $submittedToken)) {
                $errors['_form'] = '보안 토큰이 일치하지 않습니다. 페이지를 새로 고친 뒤 다시 시도해 주세요.';
            } else {
                try {
                    $normalized = $catalog->normalize($this->request->postArray('settings'), $values);
                    RuntimeConfig::replaceValues($normalized);
                    $values = array_replace($values, $normalized);
                    $saved = true;
                } catch (SettingsValidationException $exception) {
                    $errors = $exception->errors;
                }
            }
        }

        return [
            'view_name' => 'config',
            'title' => '사이트 설정',
            'data' => [
                'sections' => $this->sections($catalog, $values, $errors),
                'token' => $token,
                'errors' => $errors,
                'saved' => $saved,
            ],
            'menus' => [],
            'customData' => [],
        ];
    }

    private function canManageSettings(): bool
    {
        $member = $this->session['member'] ?? null;
        if (!is_array($member)) {
            return false;
        }

        $uuid = $this->session['uuid'] ?? null;
        $username = $member['username'] ?? null;
        if (!is_string($uuid) || !is_string($username)) {
            return false;
        }

        $permissions = [];
        ACL::getAccountPerms($uuid, $username, $permissions);

        return in_array('config', $permissions, true) || in_array('developer', $permissions, true);
    }

    private function csrfToken(): string
    {
        $token = $this->session['config_token'] ?? null;
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session['config_token'] = $token;
        }

        return $token;
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     * @return array<string, list<array<string, mixed>>>
     */
    private function sections(SettingsCatalog $catalog, array $values, array $errors): array
    {
        $sections = [];

        foreach ($catalog->all() as $definition) {
            $value = $values[$definition->key] ?? $definition->default;
            if (!is_scalar($value)) {
                $value = $definition->default;
            }

            $sections[$definition->section][] = $this->viewSetting(
                $definition,
                (string) $value,
                $errors[$definition->key] ?? null,
            );
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    private function viewSetting(SettingDefinition $definition, string $value, ?string $error): array
    {
        return [
            'key' => $definition->key,
            'label' => $definition->label,
            'description' => $definition->description,
            'type' => $definition->type->value,
            'value' => $value,
            'choices' => $definition->choices,
            'error' => $error,
        ];
    }
}
