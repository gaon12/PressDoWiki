<?php

namespace PressDo\App\Helpers;

use GeoIp2\Database\Reader;
use Throwable;

class GeoIp
{
    public const GEOCODES = ['AD','AE','AF','AG','AI','AL','AM','AO','AQ','AR','AS','AT','AU','AW','AX','AZ','BA','BB','BD','BE','BF','BG','BH','BI','BJ','BL','BM','BN','BO','BQ','BR','BS','BT','BV','BW','BY','BZ','CA','CC','CD','CF','CG','CH','CI','CK','CL','CM','CN','CO','CR','CU','CV','CW','CX','CY','CZ','DE','DJ','DK','DM','DO','DZ','EC','EE','EG','EH','ER','ES','ET','FI','FJ','FK','FM','FO','FR','GA','GB','GD','GE','GF','GG','GH','GI','GL','GM','GN','GP','GQ','GR','GS','GT','GU','GW','GY','HK','HM','HN','HR','HT','HU','ID','IE','IL','IM','IN','IO','IQ','IR','IS','IT','JE','JM','JO','JP','KE','KG','KH','KI','KM','KN','KP','KR','KW','KY','KZ','LA','LB','LC','LI','LK','LR','LS','LT','LU','LV','LY','MA','MC','MD','ME','MF','MG','MH','MK','ML','MM','MN','MO','MP','MQ','MR','MS','MT','MU','MV','MW','MX','MY','MZ','NA','NC','NE','NF','NG','NI','NL','NO','NP','NR','NU','NZ','OM','PA','PE','PF','PG','PH','PK','PL','PM','PN','PR','PS','PT','PW','PY','QA','RE','RO','RS','RU','RW','SA','SB','SC','SD','SE','SG','SH','SI','SJ','SK','SL','SM','SN','SO','SR','SS','ST','SV','SX','SY','SZ','TC','TD','TF','TG','TH','TJ','TK','TL','TM','TN','TO','TR','TT','TV','TW','TZ','UA','UG','UM','US','UY','UZ','VA','VC','VE','VG','VI','VN','VU','WF','WS','YE','YT','ZA','ZM','ZW'];

    private static ?Reader $reader = null;

    private static ?string $readerPath = null;

    /**
     * Open the configured database once per request.
     */
    private static function reader(): ?Reader
    {
        $configuredPath = Config::get('wiki.geoip2_database');
        if (!is_string($configuredPath) || $configuredPath === '' || !is_readable($configuredPath)) {
            return null;
        }

        if (self::$reader === null || self::$readerPath !== $configuredPath) {
            self::$reader = new Reader($configuredPath);
            self::$readerPath = $configuredPath;
        }

        return self::$reader;
    }

    /**
     * Resolve a visitor's timezone when a usable GeoIP database is installed.
     *
     * GeoIP is optional. Invalid, private, or unknown addresses must not prevent
     * the wiki from starting, so callers receive null and use the site timezone.
     */
    public static function getTimezone(string $ip): ?string
    {
        if (inet_pton($ip) === false) {
            return null;
        }

        try {
            return self::reader()?->city($ip)->location->timeZone;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get an ISO 3166-1 alpha-2 country code for an IP address.
     */
    public static function get(string $ip): string
    {
        if (inet_pton($ip) === false) {
            throw new \ErrorException('Must provide a valid IP address to a GeoIP function.');
        }

        try {
            $countryCode = self::reader()?->city($ip)->country->isoCode;
            if ($countryCode === null || !in_array($countryCode, self::GEOCODES, true)) {
                return 'UNAVAILABLE';
            }

            return $countryCode;
        } catch (Throwable) {
            // Geolocation is an optional ACL signal. A missing record or damaged
            // local database must fail closed instead of taking down the request.
            return 'UNAVAILABLE';
        }
    }
}
