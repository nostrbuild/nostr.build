<?php declare(strict_types=1);

/**
 * Team Cymru IP-to-ASN DNS lookup.
 *
 * Looks up ASN, announced prefix, country, registry, and AS organization name
 * for IPv4/IPv6 addresses via Team Cymru's free DNS service.
 *
 * Service: https://team-cymru.com/community-services/ip-asn-mapping/
 * Format:  TXT records under origin.asn.cymru.com / origin6.asn.cymru.com
 *
 * Each lookup performs 1-2 DNS queries (origin lookup + optional AS name).
 * Multi-origin prefixes (announced by multiple ASNs) return all ASNs in `asns`,
 * with the first as the primary `asn`.
 */
final class CymruWhois
{
    private const ORIGIN_V4 = 'origin.asn.cymru.com';
    private const ORIGIN_V6 = 'origin6.asn.cymru.com';
    private const ASNAME    = 'asn.cymru.com';

    public function __construct(
        private readonly bool $resolveAsName = true,
    ) {}

    /**
     * Look up info for an IP address.
     *
     * @return array{
     *   ip: string,
     *   asn: ?int,
     *   asns: list<int>,
     *   as_name: ?string,
     *   prefix: ?string,
     *   country: ?string,
     *   registry: ?string,
     *   allocated: ?string
     * }|null Returns null if the IP is invalid, private, or no record was found.
     */
    public function lookup(string $ip): ?array
    {
        $ip = trim($ip);

        // Reject private/reserved ranges — they aren't in the global routing
        // table and Cymru won't have a record for them.
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
            return null;
        }

        $bin = @inet_pton($ip);
        if ($bin === false) {
            return null;
        }

        $query = strlen($bin) === 4
            ? $this->buildV4Query($ip)
            : $this->buildV6Query($bin);

        $txt = $this->fetchTxt($query);
        if ($txt === null) {
            return null;
        }

        // Format: "ASN[ ASN ...] | Prefix | CC | Registry | Allocated"
        $parts = array_map('trim', explode('|', $txt));
        if (count($parts) < 5) {
            return null;
        }

        $asnsRaw = preg_split('/\s+/', $parts[0], -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $asns = [];
        foreach ($asnsRaw as $a) {
            if (ctype_digit($a)) {
                $asns[] = (int) $a;
            }
        }
        $primary = $asns[0] ?? null;

        $asName = ($this->resolveAsName && $primary !== null)
            ? $this->lookupAsName($primary)
            : null;

        return [
            'ip'        => $ip,
            'asn'       => $primary,
            'asns'      => $asns,
            'as_name'   => $asName,
            'prefix'    => $parts[1] !== '' ? $parts[1] : null,
            'country'   => $parts[2] !== '' ? $parts[2] : null,
            'registry'  => $parts[3] !== '' ? $parts[3] : null,
            'allocated' => $parts[4] !== '' ? $parts[4] : null,
        ];
    }

    /**
     * Look up the human-readable name for an ASN. Returns null if not found.
     */
    public function lookupAsName(int $asn): ?string
    {
        if ($asn <= 0) return null;

        $txt = $this->fetchTxt("AS{$asn}." . self::ASNAME);
        if ($txt === null) return null;

        // Format: "ASN | CC | Registry | Allocated | Name, CC"
        $parts = array_map('trim', explode('|', $txt));
        $name = $parts[4] ?? '';
        return $name !== '' ? $name : null;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    private function buildV4Query(string $ip): string
    {
        $reversed = implode('.', array_reverse(explode('.', $ip)));
        return $reversed . '.' . self::ORIGIN_V4;
    }

    private function buildV6Query(string $bin): string
    {
        // Nibble-reverse the 16-byte address: each hex digit becomes a label
        // in reverse order. 2001:db8::1 -> 1.0.0.0...8.b.d.0.1.0.0.2
        $hex = bin2hex($bin); // 32 chars
        $nibbles = implode('.', array_reverse(str_split($hex)));
        return $nibbles . '.' . self::ORIGIN_V6;
    }

    /**
     * Fetch a single TXT record. Returns the first non-empty TXT body, or null.
     */
    private function fetchTxt(string $hostname): ?string
    {
        $records = @dns_get_record($hostname, DNS_TXT);
        if (!is_array($records) || $records === []) {
            return null;
        }

        foreach ($records as $r) {
            $txt = '';
            if (isset($r['txt'])) {
                $txt = is_array($r['txt']) ? implode('', $r['txt']) : (string) $r['txt'];
            }
            // Some resolvers return chunked TXT under 'entries' instead.
            if ($txt === '' && isset($r['entries']) && is_array($r['entries'])) {
                $txt = implode('', $r['entries']);
            }
            if ($txt !== '') {
                return $txt;
            }
        }

        return null;
    }
}