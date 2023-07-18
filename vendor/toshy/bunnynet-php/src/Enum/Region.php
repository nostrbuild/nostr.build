<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Enum;

enum Region
{
    /** Falkenstein / Frankfurt (Germany) | Main */
    case DE;
    case FS;

    /** London (United Kingdom) | Main */
    case UK;

    /** Norway (Stockholm) | Main */
    case SE;

    /** Prague (Czech Republic) */
    case CZ;

    /** Madrid (Spain) */
    case ES;

    /** New York (United States East) | Main */
    case NY;

    /** Los Angeles (United States West) | Main */
    case LA;

    /** Seattle (United States West) */
    case WA;

    /** Miami (United States East) */
    case MI;

    /** Singapore (Singapore) | Main */
    case SG;

    /** Hong Kong (SAR of China) */
    case HK;

    /** Tokyo (Japan) */
    case JP;

    /** Sydney (Oceania) | Main */
    case SYD;

    /** Sao Paolo (Brazil) | Main */
    case BR;

    /** Johannesburg (Africa) | Main */
    case JH;

    public function host(): string
    {
        $subdomain = sprintf('%s.', strtolower($this->name));
        if (in_array($this->name, [self::DE->name, self::FS->name], true) === true) {
            $subdomain = '';
        }
        return $subdomain . Host::STORAGE_ENDPOINT;
    }
}
