<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/game/include/Game/Admin/QrCode.php';

use Game\Admin\QrCode;

function failAdminQrContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

// Published vectors: "HELLO WORLD" at 1-M has data codewords 32 91 11 120 209 114 220 77 67 64 236 17 236 17 236 17 and these 10 EC codewords.
$ec = QrCode::reedSolomon([32, 91, 11, 120, 209, 114, 220, 77, 67, 64, 236, 17, 236, 17, 236, 17], 10);
if ($ec !== [196, 35, 39, 119, 235, 215, 231, 226, 93, 23]) {
    failAdminQrContract('Reed-Solomon must match the published 1-M vector.');
}
// ISO 18004 format information: level M, mask 0 is 101010000010010; version 7 information is 000111110010010100.
if (QrCode::formatBits(0) !== 0b101010000010010 || QrCode::versionBits(7) !== 0b000111110010010100) {
    failAdminQrContract('Format and version information must match the specification.');
}

$matrix = QrCode::matrix('otpauth://t');
$size = count($matrix);
if ($size !== 21 || count($matrix[0]) !== 21) {
    failAdminQrContract('A short payload must fit version 1 (21 x 21).');
}
// The three finder patterns (7x7 ring with a 3x3 centre) and the dark module.
foreach ([[0, 0], [14, 0], [0, 14]] as [$x0, $y0]) {
    for ($y = 0; $y < 7; ++$y) {
        for ($x = 0; $x < 7; ++$x) {
            $ring = $x === 0 || $y === 0 || $x === 6 || $y === 6;
            $centre = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
            if ($matrix[$y0 + $y][$x0 + $x] !== ($ring || $centre)) {
                failAdminQrContract('A finder pattern is malformed.');
            }
        }
    }
}
if ($matrix[13][8] !== true) {
    failAdminQrContract('The fixed dark module is missing.');
}
for ($i = 8; $i < 13; ++$i) {
    if ($matrix[6][$i] !== ($i % 2 === 0) || $matrix[$i][6] !== ($i % 2 === 0)) {
        failAdminQrContract('The timing patterns are malformed.');
    }
}
if (QrCode::matrix('otpauth://t') !== $matrix) {
    failAdminQrContract('Encoding must be deterministic.');
}

$uri = 'otpauth://totp/Travian%20local:player?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&issuer=Travian%20local&algorithm=SHA1&digits=6&period=30';
$uriSize = count(QrCode::matrix($uri));
if ($uriSize < 41 || $uriSize > 57 || ($uriSize - 17) % 4 !== 0) {
    failAdminQrContract('A typical enrolment URI must use a mid-size version.');
}
$svg = QrCode::svg($uri);
if (!str_starts_with($svg, '<svg ') || !str_contains($svg, 'role="img"') || str_contains($svg, 'http://') && !str_contains($svg, 'xmlns="http://www.w3.org/2000/svg"')) {
    failAdminQrContract('The SVG must be self-contained inline markup.');
}
try {
    QrCode::matrix(str_repeat('x', 300));
    failAdminQrContract('A payload beyond version 10 must be refused.');
} catch (InvalidArgumentException) {
}

echo "Admin QR code contract passed.\n";
