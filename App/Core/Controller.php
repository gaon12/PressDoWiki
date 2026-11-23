<?php

namespace PressDo\App\Core;

use PressDo\App\Helpers\{Config, DefaultConfig, Languages, Namespaces};
use PressDo\App\Models\Member;
use PressDo\App\Services\Mark\MarkHandler;
use PressDo\App\Services\Mark\MarkupResult;
use SVG\SVG;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\{Mailer, Transport};
use Symfony\Component\Mime\{Address, Email};
use Symfony\Component\Mime\Crypto\SMimeSigner;

class Controller
{
    public object $uri_data;

    public array $error;
    public array $session;
    public array $api_config = [];
    public array $array;
    public array $dataset;
    public Request $request;
    public $page;
    public $alert;

    public function __construct()
    {
        $this->request = Request::fromGlobals();

        if (!empty($_SESSION)) {
            $this->session = $_SESSION;
        } else {
            $this->session = [
                'menus' => [],
                'member' => null,
                'ip' => self::getIPAddr(),
                'ua' => $this->request->serverString('HTTP_USER_AGENT'),
            ];
        }

        if (empty($this->session['member'])) {
            $uuid = Member::checkCookie('szczecin', $this->request->cookieString('szczecin'));
            if ($uuid !== null) {
                $member = Member::login($uuid, $this->session['ip'], $this->request->serverString('HTTP_USER_AGENT'));
                $this->session = array_replace(
                    $this->session,
                    self::getMemberData($uuid, $member['email'], $member['username'], $member['skin']),
                );
            } else {
                $ipUuid = Member::getIpUuid($this->session['ip'], true);
                if ($ipUuid !== null) {
                    $this->session['uuid'] = $ipUuid;
                }
            }
        }

        if (Config::get('wiki.use_captcha')) {
            $captchaClassName = 'PressDo\App\Helpers\Captcha\\' . DefaultConfig::get('captcha.type');
            $this->api_config = [
                'captcha_api_endpoint' => $captchaClassName::API_ENDPOINT,
                'captcha_class_name' => $captchaClassName::CLASS_NAME,
                'captcha_token_name' => $captchaClassName::TOKEN_NAME,
            ];
        }
    }

    public static function getMemberData(string $uuid, string $email, string $username, string $skin): array
    {
        $menus = [];
        $SP = ['aclgroup', 'grant', 'login_history'];
        $link = [
            'aclgroup' => '/aclgroup',
            'grant' => '/admin/grant',
            'login_history' => '/admin/login_history',
            'batch_revert' => 'batch_revert',
        ];
        $sps = Member::specialPerms($uuid);
        foreach ($SP as $prm) {
            if (in_array($prm, $sps)) {
                array_push($menus, ['l' => $link[$prm], 't' => $prm]);
            }
        }
        if (in_array('config', $sps, true) || in_array('developer', $sps, true)) {
            $menus[] = ['l' => '/admin/config', 't' => 'config', 'label' => '사이트 설정'];
            $menus[] = ['l' => '/admin/storage_integrity', 't' => 'storage_integrity', 'label' => '저장소 무결성'];
        }

        return [
            'menus' => $menus,
            //'email' => $email,
            'admin' => in_array('admin', $sps),
            'uuid' => $uuid,
            'member' => [
                'user_document_discuss' => null,
                'username' => $username,
                'gravatar_url' => '//www.gravatar.com/avatar/' . md5($email) . '?d=retro',
                'admin' => in_array('admin', $sps),
                'settings' => ['skin' => $skin],
            ],
        ];
    }

    /**
     * Render document content with syntax
     *
     * @param string $content   document content
     * @param array $options    renderer options
     */
    protected static function readSyntax(string $content, array $options = []): MarkupResult
    {
        return MarkHandler::load($content, $options);
    }

    /**
     * Returns HTML.
     * @return string
     */
    private function getPage(): string
    {
        $View = new View($this->session);
        $View->renderInit();

        $license = ($this->dataset['page']['view_name'] ?? null) === 'License' ? json_decode(file_get_contents(ProjectPaths::config('license.json')), true) : null;

        $paramSet = [
            'wiki' => $this->dataset,
            'config' => Config::all(),
            'namespace' => Namespaces::all(),
            'lang' => Languages::all(),
            'skinConfig' => $View->skin->config,
            'skinName' => $View->skin->name,
            'uri_data' => (array) $this->uri_data,
            'request_uri' => $this->request->serverString('REQUEST_URI'),
            'search_query' => $this->request->queryString('q'),
            'post' => $this->request->postData(),
            'query' => $this->request,
            'license' => $license,
            'api_config' => $this->api_config,
        ];

        if (isset($this->error)) {
            $paramSet['error'] = (array) $this->error;
        } else {
            $paramSet['error'] = [];
        }

        if (isset($this->alert)) {
            $paramSet['alert'] = (array) $this->alert;
        } else {
            $paramSet['alert'] = [];
        }

        $View->params = $paramSet;
        return $View->renderPage();
    }

    public function makePage(bool $getPage = true)
    {
        $this->dataset = (array) [
            //'local_config' => $local_config,
            'page' => $this->page,
            'session' => $this->session,
        ];

        if ($getPage) {
            echo $this->getPage();
        }
    }

    /**
     * Parse namespace and title in full title.
     *
     * @param string $title     Full title of document
     * @return array{0: string, 1: string} Namespace and title
     */
    public static function parseTitle(string $title): array
    {
        $t = explode(':', $title);

        if (!in_array($t[0], Namespaces::all()) || count($t) === 1) {
            return [Namespaces::document(), $title];
        } else {
            return [$t[0], implode(':', array_slice($t, 1))];
        }
    }

    /**
     * Make a full title with namespace and title.
     *
     * @param string $namespace     raw namespace of document
     * @param string $title     title of document
     * @return string           formed title
     */
    public static function makeTitle(string $namespace, string $title): string
    {
        if (self::forceShowNamespace($namespace, $title) === false) {
            return $title;
        } else {
            return $namespace . ':' . $title;
        }
    }

    protected static function forceShowNamespace(string $namespace, string $title): bool|null
    {
        $e = explode(':', $title);
        if (!in_array($e[0], Namespaces::all()) && $namespace == Namespaces::document()) {
            return false;
        } else {
            return null;
        }
    }

    protected static function sendMail(string $recipient, string $title, string $content): bool
    {
        $mail = DefaultConfig::get('mail.smtp_password');
        $dsn = 'smtp://' . DefaultConfig::get('mail.smtp_username') . ':' . DefaultConfig::get('mail.smtp_password') . '@'
            . DefaultConfig::get('mail.smtp_host') . ':' . DefaultConfig::get('mail.smtp_port');
        $mailer = new Mailer(Transport::fromDsn($dsn));
        $email = (new Email())
            ->from(new Address(DefaultConfig::get('mail.smtp_address'), Config::get('wiki.site_name_en')))
            ->to($recipient)
            ->subject($title)
            ->html($content);

        if (
            DefaultConfig::get('mail.sign_smime') === true
            && file_exists(ProjectPaths::config('certificate.crt')) && file_exists(ProjectPaths::config('privkey.key'))) {
            $signer = new SMimeSigner(ProjectPaths::config('certificate.crt'), ProjectPaths::config('privkey.key'), DefaultConfig::get('mail.smime_passphrase'));
            $email = $signer->sign($email);
        }

        try {
            $mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            //error_log($e->getDebug());
            return false;
        }
        return true;
    }

    protected static function editFormProcess(Controller &$page, string $tokennm): bool
    {
        $submittedContent = $page->request->postScalarString('content');
        if ($submittedContent === null) {
            return false;
        }

        $content = preg_replace('/^#(redirect|넘겨주기) (.+)$/im', '#redirect $2', $submittedContent);

        // ignore string after redirect
        if (preg_match('/^#redirect (.+)$/im', $content, $matches)) {
            $content = $matches[0];
        }

        $submittedToken = $page->request->postString('token');
        $sessionToken = $page->session[$tokennm] ?? '';

        if ($sessionToken === '' || !hash_equals((string) $sessionToken, $submittedToken)) {
            // Reject: wrong anti-CSRF token
            $page->error = self::makeErrorBox('err_csrf_token');
        } elseif (($page->session['raw'] ?? null) === $content) {
            // Reject: same doc content
            $page->error = self::makeErrorBox('err_same_contents');
        } else {
            // Approve Edit
            $page->content = $content;
            return true;
        }
        return false;
    }

    protected static function validateCaptcha(?string $token): bool
    {
        if (!Config::get('wiki.use_captcha')) {
            return true;
        }

        if (empty($token)) {
            return false;
        }

        $captchaClassName = 'PressDo\App\Helpers\Captcha\\' . DefaultConfig::get('captcha.type');
        return $captchaClassName::verify($token);
    }

    protected static function makeErrorBox(string $code)
    {
        return [
            'code' => $code,
            'message' => Languages::get('msg', $code) ?? $code,
            'errbox' => true,
        ];
    }

    /**
     * Load diff between two strings in HTML
     * @param string $old
     * @param string $new
     * @param string $caption
     * @return string
     */
    protected static function loadDiff(string $old, string $new, string $caption = ''): string
    {
        require ProjectPaths::app('Helpers/Libraries/diff/Diff.php');
        require ProjectPaths::app('Helpers/Libraries/diff/Inline.php');

        $a = explode("\n", $old);
        $b = explode("\n", $new);

        if ($old === $new) {
            return self::diffSame($a, $caption);
        }

        $options = [
            //'ignoreWhitespace' => true,
            //'ignoreCase' => true,
        ];

        $diff = new \Diff($a, $b, $options);
        $ren = new \Diff_Renderer_Html_Inline();
        $ren->caption = $caption;
        $ren->linecnt = [count($a),count($b)];

        return $diff->render($ren);
    }

    protected static function diffSame(array $lines, string $caption)
    {
        $html = '<table class="diff">'
            . '<thead>'
            . '<tr>'
            . '<th></th>'
            . '<th></th>'
            . '<th class="diff">' . $caption . '</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>';
        $len = count($lines);

        for ($i = 0; $i < $len; $i++) {
            $html .= '<tr><th>' . $i . '</th><th>' . $i . '</th><td class="equal"><div>' . htmlspecialchars($lines[$i]) . '</div></td></tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Get IP of user.
     */
    public static function getIPAddr(): string
    {
        return Request::fromGlobals()->serverString('REMOTE_ADDR');
    }

    public static function formatTime(int $sec): array
    {
        $week = floor($sec / 604800);
        $sec -= $week * 604800;
        $day = floor($sec / 86400);
        $sec -= $day * 86400;
        $hour = floor($sec / 3600);
        $sec -= $hour * 3600;
        $min = floor($sec / 60);
        $sec -= $min * 60;
        return ['week' => $week, 'day' => $day, 'hour' => $hour, 'minute' => $min, 'second' => $sec];
    }

    /**
     * Generate random string
     * @param int $len  length of string
     * @param bool $u   if use uppercase string
     * @param string $add additional string included to generated one.
     * @return string   generated string
     */
    protected static function rand(int $len = 16, bool $u = false, string $add = ''): string
    {
        $c = '0123456789abcdefghijklmnopqrstuvwxyz';
        if ($u) {
            $c .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        if (strlen($add) > 0) {
            $c .= $add;
        }

        $cl = strlen($c);
        $s = '';
        for ($i = 0; $i < $len; $i++) {
            $s .= $c[random_int(0, $cl - 1)];
        }

        return $s;
    }

    public static function getTransparentBackground(int $width, int $height): string
    {
        $image = new SVG($width, $height);
        return (string) $image;
    }

    public static function getCookieOptions(int $duration): array
    {
        return [
            'expires' => $_SERVER['REQUEST_TIME'] + $duration,
            'path' => '/',
            'domain' => Config::get('wiki.domain'),
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
