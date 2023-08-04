<?php

/**
 * Summary of Bech32
 */
class Bech32
{
  const ALPHABET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
  /**
   * Summary of ALPHABET_MAP
   * @var array
   */
  private $ALPHABET_MAP = [];

  /**
   * Summary of __construct
   */
  public function __construct()
  {
    for ($i = 0; $i < strlen(self::ALPHABET); $i++) {
      $this->ALPHABET_MAP[self::ALPHABET[$i]] = $i;
    }
  }

  /**
   * Summary of polymodStep
   * @param mixed $pre
   * @return int
   */
  private function polymodStep($pre): int
  {
    $b = $pre >> 25;
    return (
      (($pre & 0x1ffffff) << 5) ^
      ((($b >> 0) & 1 ? 0x3b6a57b2 : 0) ^
        (($b >> 1) & 1 ? 0x26508e6d : 0) ^
        (($b >> 2) & 1 ? 0x1ea119fa : 0) ^
        (($b >> 3) & 1 ? 0x3d4233dd : 0) ^
        (($b >> 4) & 1 ? 0x2a1462b3 : 0))
    );
  }

  /**
   * Summary of prefixChk
   * @param mixed $prefix
   * @throws \Exception
   * @return int
   */
  private function prefixChk($prefix): int
  {
    $chk = 1;
    for ($i = 0; $i < strlen($prefix); $i++) {
      $c = ord($prefix[$i]);
      if ($c < 33 || $c > 126)
        throw new Exception('Invalid prefix (' . $prefix . ')');
      $chk = $this->polymodStep($chk) ^ ($c >> 5);
    }
    $chk = $this->polymodStep($chk);
    for ($i = 0; $i < strlen($prefix); $i++) {
      $v = ord($prefix[$i]);
      $chk = $this->polymodStep($chk) ^ ($v & 0x1f);
    }
    return $chk;
  }

  /**
   * Summary of convertbits
   * @param mixed $data
   * @param mixed $inBits
   * @param mixed $outBits
   * @param mixed $pad
   * @throws \Exception
   * @return array
   */
  private function convertbits($data, $inBits, $outBits, $pad = true): array
  {
    $value = 0;
    $bits = 0;
    $maxV = (1 << $outBits) - 1;
    $result = [];
    for ($i = 0; $i < sizeof($data); $i++) {
      $value = ($value << $inBits) | $data[$i];
      $bits += $inBits;
      while ($bits >= $outBits) {
        $bits -= $outBits;
        array_push($result, ($value >> $bits) & $maxV);
      }
    }
    if ($pad) {
      if ($bits > 0) {
        array_push($result, ($value << ($outBits - $bits)) & $maxV);
      }
    } else {
      if ($bits >= $inBits)
        throw new Exception('Excess padding');
      if (($value << ($outBits - $bits)) & $maxV)
        throw new Exception('Non-zero padding');
    }
    return $result;
  }

  /**
   * Summary of toWords
   * @param mixed $bytes
   * @return array
   */
  private function toWords($bytes): array
  {
    return $this->convertbits($bytes, 8, 5, true);
  }

  /**
   * Summary of fromWords
   * @param mixed $words
   * @return array
   */
  private function fromWords($words): array
  {
    return $this->convertbits($words, 5, 8, false);
  }

  /**
   * Summary of encode
   * @param mixed $prefix
   * @param mixed $words
   * @param mixed $encoding
   * @throws \Exception
   * @return string
   */
  public function encode($prefix, $words, $encoding = 'bech32'): string
  {
    $chk = $this->prefixChk($prefix);
    $result = strtolower($prefix) . '1';
    for ($i = 0; $i < sizeof($words); $i++) {
      $x = $words[$i];
      if ($x >> 5 !== 0)
        throw new Exception('Non 5-bit word');
      $chk = $this->polymodStep($chk) ^ $x;
      $result .= self::ALPHABET[$x];
    }
    $encoding_const = ($encoding === 'bech32') ? 1 : 0x2bc830a3;
    for ($i = 0; $i < 6; $i++) {
      $chk = $this->polymodStep($chk);
    }
    $chk ^= $encoding_const;
    for ($i = 0; $i < 6; $i++) {
      $v = ($chk >> ((5 - $i) * 5)) & 0x1f;
      $result .= self::ALPHABET[$v];
    }
    return $result;
  }

  /**
   * Summary of decode
   * @param mixed $str
   * @param mixed $encoding
   * @throws \Exception
   * @return array
   */
  public function decode($str, $encoding = 'bech32'): array
  {
    if (strlen($str) < 8)
      throw new Exception('Too short to be a valid bech32 string');
    $str = strtolower($str);
    $split = strrpos($str, '1');
    if ($split === false)
      throw new Exception('No separator character for ' . $str);
    $prefix = substr($str, 0, $split);
    $wordChars = substr($str, $split + 1);
    $chk = $this->prefixChk($prefix);
    $words = [];
    $encoding_const = ($encoding === 'bech32') ? 1 : 0x2bc830a3;
    for ($i = 0; $i < strlen($wordChars); $i++) {
      $c = $wordChars[$i];
      $v = $this->ALPHABET_MAP[$c] ?? false;
      if ($v === false)
        throw new Exception('Unknown character ' . $c);
      $chk = $this->polymodStep($chk) ^ $v;
      if ($i + 6 >= strlen($wordChars))
        continue;
      $words[] = $v;
    }
    if ($chk !== $encoding_const)
      throw new Exception('Invalid checksum for ' . $str);
    return ['prefix' => $prefix, 'words' => $words];
  }

  // Hex to Bech32
  /**
   * Summary of convertHexToBech32
   * @param mixed $prefix
   * @param mixed $hex
   * @return string
   */
  public function convertHexToBech32($prefix, $hex): string
  {
    // Split the hex string into an array of integers
    $data = array_map('hexdec', str_split($hex, 2));
    return $this->encode($prefix, $this->toWords($data));
  }

  // Bech32 to Hex
  /**
   * Summary of convertBech32ToHex
   * @param mixed $bech32Str
   * @return string
   */
  public function convertBech32ToHex($bech32Str): string
  {
    // Extract data part
    $data = $this->decode($bech32Str)['words'];
    $bytes = $this->fromWords($data);

    // Convert to hex, ensuring that each byte is two digits
    $hex = array_map(function ($byte) {
      return str_pad(dechex($byte), 2, '0', STR_PAD_LEFT);
    }, $bytes);

    return implode('', $hex);
  }

  public function isValidNpub1Address($address): bool
  {
    try {
      $decoded = $this->decode($address);
      if ($decoded['prefix'] !== 'npub') {
        return false;
      }
      return true;
    } catch (Exception $e) {
      // Decoding failed, so the address is not valid
      return false;
    }
  }
}
