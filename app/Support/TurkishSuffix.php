<?php

namespace App\Support;

/**
 * Turkish suffixes obey vowel harmony, so the certificate sentence
 * "<course>'na katılmıştır" is only correct for half of all course titles:
 * a title whose last vowel is a front vowel takes -ne ("Güvenliği'ne"),
 * a back vowel takes -na ("Atölyesi'na" -> "Atölyesi'ne" is wrong, but
 * "Programı'na" is right). Hard-coding either one misspells the other half,
 * which is very visible on a printed certificate.
 */
class TurkishSuffix
{
    private const BACK_VOWELS = ['a', 'ı', 'o', 'u', 'â'];

    private const FRONT_VOWELS = ['e', 'i', 'ö', 'ü'];

    /**
     * The dative suffix ("to the X") for a name already ending in a
     * possessive vowel -- the "-na / -ne" in "<course>'na katılmıştır".
     */
    public static function dative(string $word): string
    {
        return self::lastVowelIsFront($word) ? 'ne' : 'na';
    }

    private static function lastVowelIsFront(string $word): bool
    {
        $lower = mb_strtolower($word, 'UTF-8');
        $length = mb_strlen($lower, 'UTF-8');

        for ($i = $length - 1; $i >= 0; $i--) {
            $char = mb_substr($lower, $i, 1, 'UTF-8');
            if (in_array($char, self::FRONT_VOWELS, true)) {
                return true;
            }
            if (in_array($char, self::BACK_VOWELS, true)) {
                return false;
            }
        }

        // No vowel at all (an acronym, say) -- back harmony is the safer default.
        return false;
    }
}
