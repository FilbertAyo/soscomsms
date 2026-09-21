<?php

namespace App\Services;

use App\Enums\MessageEncoding;

class SegmentationService
{
    /**
     * GSM 03.38 default alphabet (basic characters count as 1 septet).
     *
     * @var list<string>
     */
    private const GSM_BASIC = [
        '@', '£', '$', '¥', 'è', 'é', 'ù', 'ì', 'ò', 'Ç', "\n", 'Ø', 'ø', "\r", 'Å', 'å',
        'Δ', '_', 'Φ', 'Γ', 'Λ', 'Ω', 'Π', 'Ψ', 'Σ', 'Θ', 'Ξ', 'Æ', 'æ', 'ß', 'É',
        ' ', '!', '"', '#', '¤', '%', '&', "'", '(', ')', '*', '+', ',', '-', '.', '/',
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ':', ';', '<', '=', '>', '?',
        '¡', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
        'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'Ä', 'Ö', 'Ñ', 'Ü', '§',
        '¿', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o',
        'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'ä', 'ö', 'ñ', 'ü', 'à',
    ];

    /**
     * GSM 03.38 extension table (each character uses an escape septet + itself = 2).
     *
     * @var list<string>
     */
    private const GSM_EXTENDED = ['|', '^', '€', '{', '}', '[', ']', '~', '\\'];

    /**
     * @return array{encoding: string, characterCount: int, segmentCount: int, singleLimit: int, concatLimit: int}
     */
    public function analyze(string $text): array
    {
        $gsmLength = $this->gsmLength($text);

        if ($gsmLength !== null) {
            return $this->pack(MessageEncoding::Gsm, $gsmLength, 160, 153);
        }

        return $this->pack(MessageEncoding::Unicode, $this->unicodeLength($text), 70, 63);
    }

    public function segmentCount(string $text): int
    {
        return $this->analyze($text)['segmentCount'];
    }

    private function gsmLength(string $text): ?int
    {
        $basic = array_fill_keys(self::GSM_BASIC, true);
        $extended = array_fill_keys(self::GSM_EXTENDED, true);
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $length = 0;

        foreach ($characters as $character) {
            if (isset($basic[$character])) {
                $length++;

                continue;
            }

            if (isset($extended[$character])) {
                $length += 2;

                continue;
            }

            return null;
        }

        return $length;
    }

    private function unicodeLength(string $text): int
    {
        $utf16 = mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');

        return intdiv(strlen($utf16), 2);
    }

    /**
     * @return array{encoding: string, characterCount: int, segmentCount: int, singleLimit: int, concatLimit: int}
     */
    private function pack(MessageEncoding $encoding, int $characterCount, int $single, int $concat): array
    {
        $segments = 0;
        if ($characterCount > 0) {
            $segments = $characterCount <= $single
                ? 1
                : (int) ceil($characterCount / $concat);
        }

        return [
            'encoding' => $encoding->value,
            'characterCount' => $characterCount,
            'segmentCount' => $segments,
            'singleLimit' => $single,
            'concatLimit' => $concat,
        ];
    }
}
