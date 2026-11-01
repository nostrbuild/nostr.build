<?php
// Seeded property test for aaReportedFilename (libs/ReportedFilename.funcs.php).
// Run: php tests/ReportedFilenameTest.php [seed]   (exit 0 = pass)
// Properties, over every URL shape a report arrives in and random junk:
//   - the result is null or a well-formed stored name ([A-Za-z0-9._-]{1,255});
//   - it is idempotent (a name maps to itself);
//   - every URL form wrapping a stored name returns exactly that name.
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../libs/ReportedFilename.funcs.php';

$seed = (int) ($argv[1] ?? 20261102);
mt_srand($seed);
$fail = 0;
$check = function (bool $ok, string $what) use (&$fail) {
  if (!$ok) { $fail++; fwrite(STDERR, "FAIL: $what\n"); }
};
$rand = fn(string $alphabet, int $n) => implode('', array_map(fn() => $alphabet[mt_rand(0, strlen($alphabet) - 1)], range(1, $n)));
$well = fn(?string $r) => $r === null || preg_match('/^[A-Za-z0-9._-]{1,255}\z/', $r) === 1;

$hosts = ['i.nostr.build', 'v.nostr.build', 'a.nostr.build', 'd.nostr.build', 'media.nostr.build', 'nostr.build', 'image.nostr.build'];
$exts = ['jpg', 'png', 'gif', 'webp', 'mp4', 'mov', 'mp3', 'pdf', 'zip', 'jpeg'];
for ($i = 0; $i < 3000; $i++) {
  $name = $rand('0123456789abcdef', mt_rand(1, 64)) . '.' . $exts[mt_rand(0, count($exts) - 1)];
  $host = $hosts[mt_rand(0, count($hosts) - 1)];
  $forms = [
    $name,
    "https://$host/$name",
    "https://$host/p/$name",
    "https://$host/_r/w_" . mt_rand(1, 2000) . "/$name",
    "https://$host/thumb/$name",
    "https://$host/resp/240p/$name",
    "https://$host/$name?x=" . mt_rand() . "&y=a%20b",
    "https://$host/$name#frag",
    "$name?dl=1",
    "https://$host/$name/poster.jpg",
    "https://$host/" . rawurlencode($name),
    "  https://$host/$name  ",
  ];
  foreach ($forms as $f) $check(aaReportedFilename($f) === $name, "form '$f' should give '$name'");
  $check(aaReportedFilename($name) === aaReportedFilename(aaReportedFilename($name)), "idempotent on '$name'");
}

// A poster.jpg under a directory (no extension) is not its parent's name.
foreach (['https://i.nostr.build/thumb/poster.jpg', 'https://i.nostr.build/_r/w=300/poster.jpg'] as $f) {
  $check(aaReportedFilename($f) === 'poster.jpg', "directory poster '$f'");
}

// Junk: every byte class, slashes, percent signs, very long input.
$junk = " \t\n/\\%?#:;@&=+$,<>\"'`{}|^[]~!*()" . "abcXYZ019._-" . "\xC3\xA9\xE2\x80\x94\xF0\x9F\x98\x80\x00";
for ($i = 0; $i < 20000; $i++) {
  $s = $rand($junk, mt_rand(0, 400));
  if (mt_rand(0, 3) === 0) $s = 'https://i.nostr.build/' . $s;
  $r = aaReportedFilename($s);
  $check($well($r), 'well-formed for ' . bin2hex(substr($s, 0, 40)));
  if ($r !== null) $check(aaReportedFilename($r) === $r, "idempotent on '$r'");
}
$check(aaReportedFilename(str_repeat('a', 256)) === null, 'over 255 characters is rejected');
$check(aaReportedFilename(str_repeat('a', 255)) === str_repeat('a', 255), '255 characters is accepted');

echo $fail ? "$fail failures (seed $seed)\n" : "ok (seed $seed)\n";
exit($fail ? 1 : 0);
