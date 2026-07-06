<?php

namespace App\Services\Daftra;

class Transliteration
{
    private const ARABIC_TO_LATIN = [
        'ا' => 'a', 'أ' => 'a', 'إ' => 'a', 'آ' => 'aa',
        'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j',
        'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh',
        'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh',
        'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z',
        'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
        'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
        'ه' => 'h', 'و' => 'w', 'ي' => 'y', 'ى' => 'a',
        'ة' => 'h', 'ء' => '',
        'ئ' => 'y', 'ؤ' => 'w',
        'پ' => 'p', 'چ' => 'ch', 'ڤ' => 'v', 'ڭ' => 'ng',
        'َ' => 'a', 'ُ' => 'u', 'ِ' => 'i', 'ّ' => '',
        'ْ' => '', 'ً' => 'an', 'ٌ' => 'un', 'ٍ' => 'in',
    ];

    public static function isArabic(string $text): bool
    {
        return preg_match('/\p{Arabic}/u', $text) === 1;
    }

    public static function toLatin(string $arabic): string
    {
        $result = '';
        foreach (mb_str_split($arabic) as $char) {
            if (isset(self::ARABIC_TO_LATIN[$char])) {
                $result .= self::ARABIC_TO_LATIN[$char];
            } elseif (! preg_match('/\p{Arabic}/u', $char)) {
                $result .= $char;
            }
        }

        return $result;
    }

    public static function transliterateAlternatives(string $arabic): array
    {
        $base = self::toLatin($arabic);
        $alternatives = [$base];

        $variations = [
            ['w', 'o'], ['w', 'u'], ['w', 'oo'],
            ['y', 'i'], ['y', 'ee'], ['y', 'ei'],
            ['a', 'aa'], ['a', 'e'], ['a', 'ee'],
            ['aa', 'a'],
            ['h', ''], ['h', 't'],
            ['sh', 'ch'],
            ['kh', 'h'], ['kh', 'k'],
            ['gh', 'g'], ['gh', 'ga'],
            ['q', 'k'], ['q', 'g'],
            ['th', 't'], ['th', 's'],
            ['dh', 'd'], ['dh', 'z'],
        ];

        foreach ($variations as [$from, $to]) {
            $alt = str_replace($from, $to, $base);
            if ($alt !== $base) {
                $alternatives[] = $alt;
            }
        }

        $alternatives[] = str_replace(['w', 'y'], ['o', 'i'], $base);
        $alternatives[] = preg_replace('/a{2,}/', 'a', $base);

        return array_values(array_unique(array_filter($alternatives)));
    }
}
