<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/** The few DER encodings and decodings the JWT code needs. */
final class Asn1
{
    private function __construct()
    {
    }

    private static function length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\0");

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function tlv(int $tag, string $value): string
    {
        return chr($tag) . self::length(strlen($value)) . $value;
    }

    private static function integer(string $unsigned): string
    {
        $unsigned = ltrim($unsigned, "\0");
        if ($unsigned === '' || (ord($unsigned[0]) & 0x80) !== 0) {
            $unsigned = "\0" . $unsigned;
        }

        return self::tlv(0x02, $unsigned);
    }

    /** RSA public key (n, e big-endian) as a SubjectPublicKeyInfo PEM. */
    public static function rsaPublicKeyPem(string $modulus, string $exponent): string
    {
        $rsaKey = self::tlv(0x30, self::integer($modulus) . self::integer($exponent));
        $algorithm = self::tlv(0x30, self::tlv(0x06, "\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01") . "\x05\x00");
        $der = self::tlv(0x30, $algorithm . self::tlv(0x03, "\0" . $rsaKey));

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** Converts an ASN.1 DER ECDSA signature to the fixed-width r||s form JWS requires. */
    public static function ecdsaDerToRaw(string $der, int $size): ?string
    {
        $offset = 0;
        if (strlen($der) < 8 || $der[$offset++] !== "\x30") {
            return null;
        }
        $sequenceLength = self::readLength($der, $offset);
        if ($sequenceLength === null || $offset + $sequenceLength !== strlen($der)) {
            return null;
        }
        $parts = [];
        for ($i = 0; $i < 2; ++$i) {
            if (($der[$offset++] ?? '') !== "\x02") {
                return null;
            }
            $length = self::readLength($der, $offset);
            if ($length === null || $offset + $length > strlen($der)) {
                return null;
            }
            $value = ltrim(substr($der, $offset, $length), "\0");
            $offset += $length;
            if (strlen($value) > $size) {
                return null;
            }
            $parts[] = str_pad($value, $size, "\0", STR_PAD_LEFT);
        }

        return $offset === strlen($der) ? $parts[0] . $parts[1] : null;
    }

    private static function readLength(string $der, int &$offset): ?int
    {
        $first = ord($der[$offset] ?? "\xFF");
        ++$offset;
        if ($first < 0x80) {
            return $first;
        }
        $count = $first & 0x7F;
        if ($count < 1 || $count > 2) {
            return null;
        }
        $length = 0;
        for ($i = 0; $i < $count; ++$i) {
            $length = ($length << 8) | ord($der[$offset++] ?? "\0");
        }

        return $length;
    }
}
