<?php

/**
 * The stored filename a report points to (Account Review intake): the last
 * path segment of a URL in any form (any host, /_r/ resize, thumb / resp
 * paths, a query string or fragment) or a bare filename. A derivative's
 * trailing /poster.jpg names its parent file when the parent is a file (has
 * an extension). Null when the result can't be a stored filename. Pure: the
 * seeded property test is tests/ReportedFilenameTest.php.
 */
function aaReportedFilename(string $q): ?string
{
  $q = trim($q);
  $path = str_contains($q, '://') ? (string) parse_url($q, PHP_URL_PATH) : preg_split('/[?#]/', $q)[0];
  $segs = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));
  if ($segs === []) return null;
  $name = rawurldecode($segs[count($segs) - 1]);
  if (strcasecmp($name, 'poster.jpg') === 0 && count($segs) > 1) {
    $parent = rawurldecode($segs[count($segs) - 2]);
    if (preg_match('/^[^.]+\.[A-Za-z0-9]{1,8}\z/', $parent)) $name = $parent;
  }
  return preg_match('/^[A-Za-z0-9._-]{1,255}\z/', $name) ? $name : null;
}
