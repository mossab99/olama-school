<?php

namespace PhpOffice\PhpSpreadsheet\Shared;

class StringHelper
{
    /**
     * Control characters array.
     *
     * @var string[]
     */
    private static $controlCharacters = [];

    /**
     * SYLK Characters array.
     *
     * @var array
     */
    private static $SYLKCharacters = [];

    /**
     * Decimal separator.
     *
     * @var ?string
     */
    private static $decimalSeparator;

    /**
     * Thousands separator.
     *
     * @var ?string
     */
    private static $thousandsSeparator;

    /**
     * Currency code.
     *
     * @var string
     */
    private static $currencyCode;

    /**
     * Is iconv extension avalable?
     *
     * @var ?bool
     */
    private static $isIconvEnabled;

    /**
     * iconv options.
     *
     * @var string
     */
    private static $iconvOptions = '//IGNORE//TRANSLIT';

    /**
     * Build control characters array.
     */
    private static function buildControlCharacters(): void
    {
        for ($i = 0; $i <= 31; ++$i) {
            if ($i != 9 && $i != 10 && $i != 13) {
                $find = '_x' . sprintf('%04s', strtoupper(dechex($i))) . '_';
                $replace = chr($i);
                self::$controlCharacters[$find] = $replace;
            }
        }
    }

    /**
     * Build SYLK characters array.
     */
    private static function buildSYLKCharacters(): void
    {
        self::$SYLKCharacters = [
            "\x1B 0" => chr(0),
            "\x1B 1" => chr(1),
            "\x1B 2" => chr(2),
            "\x1B 3" => chr(3),
            "\x1B 4" => chr(4),
            "\x1B 5" => chr(5),
            "\x1B 6" => chr(6),
            "\x1B 7" => chr(7),
            "\x1B 8" => chr(8),
            "\x1B 9" => chr(9),
            "\x1B :" => chr(10),
            "\x1B ;" => chr(11),
            "\x1B <" => chr(12),
            "\x1B =" => chr(13),
            "\x1B >" => chr(14),
            "\x1B ?" => chr(15),
            "\x1B!0" => chr(16),
            "\x1B!1" => chr(17),
            "\x1B!2" => chr(18),
            "\x1B!3" => chr(19),
            "\x1B!4" => chr(20),
            "\x1B!5" => chr(21),
            "\x1B!6" => chr(22),
            "\x1B!7" => chr(23),
            "\x1B!8" => chr(24),
            "\x1B!9" => chr(25),
            "\x1B!:" => chr(26),
            "\x1B!;" => chr(27),
            "\x1B!<" => chr(28),
            "\x1B!=" => chr(29),
            "\x1B!>" => chr(30),
            "\x1B!?" => chr(31),
            "\x1B'?" => chr(127),
            "\x1B(0" => 'â‚¬', // 128 in CP1252
            "\x1B(2" => 'â€ڑ', // 130 in CP1252
            "\x1B(3" => 'ئ’', // 131 in CP1252
            "\x1B(4" => 'â€‍', // 132 in CP1252
            "\x1B(5" => 'â€¦', // 133 in CP1252
            "\x1B(6" => 'â€ ', // 134 in CP1252
            "\x1B(7" => 'â€،', // 135 in CP1252
            "\x1B(8" => 'ث†', // 136 in CP1252
            "\x1B(9" => 'â€°', // 137 in CP1252
            "\x1B(:" => 'إ ', // 138 in CP1252
            "\x1B(;" => 'â€¹', // 139 in CP1252
            "\x1BNj" => 'إ’', // 140 in CP1252
            "\x1B(>" => 'إ½', // 142 in CP1252
            "\x1B)1" => 'â€ک', // 145 in CP1252
            "\x1B)2" => 'â€™', // 146 in CP1252
            "\x1B)3" => 'â€œ', // 147 in CP1252
            "\x1B)4" => 'â€‌', // 148 in CP1252
            "\x1B)5" => 'â€¢', // 149 in CP1252
            "\x1B)6" => 'â€“', // 150 in CP1252
            "\x1B)7" => 'â€”', // 151 in CP1252
            "\x1B)8" => 'ثœ', // 152 in CP1252
            "\x1B)9" => 'â„¢', // 153 in CP1252
            "\x1B):" => 'إ،', // 154 in CP1252
            "\x1B);" => 'â€؛', // 155 in CP1252
            "\x1BNz" => 'إ“', // 156 in CP1252
            "\x1B)>" => 'إ¾', // 158 in CP1252
            "\x1B)?" => 'إ¸', // 159 in CP1252
            "\x1B*0" => ' ', // 160 in CP1252
            "\x1BN!" => 'آ،', // 161 in CP1252
            "\x1BN\"" => 'آ¢', // 162 in CP1252
            "\x1BN#" => 'آ£', // 163 in CP1252
            "\x1BN(" => 'آ¤', // 164 in CP1252
            "\x1BN%" => 'آ¥', // 165 in CP1252
            "\x1B*6" => 'آ¦', // 166 in CP1252
            "\x1BN'" => 'آ§', // 167 in CP1252
            "\x1BNH " => 'آ¨', // 168 in CP1252
            "\x1BNS" => 'آ©', // 169 in CP1252
            "\x1BNc" => 'آھ', // 170 in CP1252
            "\x1BN+" => 'آ«', // 171 in CP1252
            "\x1B*<" => 'آ¬', // 172 in CP1252
            "\x1B*=" => 'آ­', // 173 in CP1252
            "\x1BNR" => 'آ®', // 174 in CP1252
            "\x1B*?" => 'آ¯', // 175 in CP1252
            "\x1BN0" => 'آ°', // 176 in CP1252
            "\x1BN1" => 'آ±', // 177 in CP1252
            "\x1BN2" => 'آ²', // 178 in CP1252
            "\x1BN3" => 'آ³', // 179 in CP1252
            "\x1BNB " => 'آ´', // 180 in CP1252
            "\x1BN5" => 'آµ', // 181 in CP1252
            "\x1BN6" => 'آ¶', // 182 in CP1252
            "\x1BN7" => 'آ·', // 183 in CP1252
            "\x1B+8" => 'آ¸', // 184 in CP1252
            "\x1BNQ" => 'آ¹', // 185 in CP1252
            "\x1BNk" => 'آ؛', // 186 in CP1252
            "\x1BN;" => 'آ»', // 187 in CP1252
            "\x1BN<" => 'آ¼', // 188 in CP1252
            "\x1BN=" => 'آ½', // 189 in CP1252
            "\x1BN>" => 'آ¾', // 190 in CP1252
            "\x1BN?" => 'آ؟', // 191 in CP1252
            "\x1BNAA" => 'أ€', // 192 in CP1252
            "\x1BNBA" => 'أپ', // 193 in CP1252
            "\x1BNCA" => 'أ‚', // 194 in CP1252
            "\x1BNDA" => 'أƒ', // 195 in CP1252
            "\x1BNHA" => 'أ„', // 196 in CP1252
            "\x1BNJA" => 'أ…', // 197 in CP1252
            "\x1BNa" => 'أ†', // 198 in CP1252
            "\x1BNKC" => 'أ‡', // 199 in CP1252
            "\x1BNAE" => 'أˆ', // 200 in CP1252
            "\x1BNBE" => 'أ‰', // 201 in CP1252
            "\x1BNCE" => 'أٹ', // 202 in CP1252
            "\x1BNHE" => 'أ‹', // 203 in CP1252
            "\x1BNAI" => 'أŒ', // 204 in CP1252
            "\x1BNBI" => 'أچ', // 205 in CP1252
            "\x1BNCI" => 'أژ', // 206 in CP1252
            "\x1BNHI" => 'أڈ', // 207 in CP1252
            "\x1BNb" => 'أگ', // 208 in CP1252
            "\x1BNDN" => 'أ‘', // 209 in CP1252
            "\x1BNAO" => 'أ’', // 210 in CP1252
            "\x1BNBO" => 'أ“', // 211 in CP1252
            "\x1BNCO" => 'أ”', // 212 in CP1252
            "\x1BNDO" => 'أ•', // 213 in CP1252
            "\x1BNHO" => 'أ–', // 214 in CP1252
            "\x1B-7" => 'أ—', // 215 in CP1252
            "\x1BNi" => 'أک', // 216 in CP1252
            "\x1BNAU" => 'أ™', // 217 in CP1252
            "\x1BNBU" => 'أڑ', // 218 in CP1252
            "\x1BNCU" => 'أ›', // 219 in CP1252
            "\x1BNHU" => 'أœ', // 220 in CP1252
            "\x1B-=" => 'أ‌', // 221 in CP1252
            "\x1BNl" => 'أ‍', // 222 in CP1252
            "\x1BN{" => 'أں', // 223 in CP1252
            "\x1BNAa" => 'أ ', // 224 in CP1252
            "\x1BNBa" => 'أ،', // 225 in CP1252
            "\x1BNCa" => 'أ¢', // 226 in CP1252
            "\x1BNDa" => 'أ£', // 227 in CP1252
            "\x1BNHa" => 'أ¤', // 228 in CP1252
            "\x1BNJa" => 'أ¥', // 229 in CP1252
            "\x1BNq" => 'أ¦', // 230 in CP1252
            "\x1BNKc" => 'أ§', // 231 in CP1252
            "\x1BNAe" => 'أ¨', // 232 in CP1252
            "\x1BNBe" => 'أ©', // 233 in CP1252
            "\x1BNCe" => 'أھ', // 234 in CP1252
            "\x1BNHe" => 'أ«', // 235 in CP1252
            "\x1BNAi" => 'أ¬', // 236 in CP1252
            "\x1BNBi" => 'أ­', // 237 in CP1252
            "\x1BNCi" => 'أ®', // 238 in CP1252
            "\x1BNHi" => 'أ¯', // 239 in CP1252
            "\x1BNs" => 'أ°', // 240 in CP1252
            "\x1BNDn" => 'أ±', // 241 in CP1252
            "\x1BNAo" => 'أ²', // 242 in CP1252
            "\x1BNBo" => 'أ³', // 243 in CP1252
            "\x1BNCo" => 'أ´', // 244 in CP1252
            "\x1BNDo" => 'أµ', // 245 in CP1252
            "\x1BNHo" => 'أ¶', // 246 in CP1252
            "\x1B/7" => 'أ·', // 247 in CP1252
            "\x1BNy" => 'أ¸', // 248 in CP1252
            "\x1BNAu" => 'أ¹', // 249 in CP1252
            "\x1BNBu" => 'أ؛', // 250 in CP1252
            "\x1BNCu" => 'أ»', // 251 in CP1252
            "\x1BNHu" => 'أ¼', // 252 in CP1252
            "\x1B/=" => 'أ½', // 253 in CP1252
            "\x1BN|" => 'أ¾', // 254 in CP1252
            "\x1BNHy" => 'أ؟', // 255 in CP1252
        ];
    }

    /**
     * Get whether iconv extension is available.
     *
     * @return bool
     */
    public static function getIsIconvEnabled()
    {
        if (isset(self::$isIconvEnabled)) {
            return self::$isIconvEnabled;
        }

        // Assume no problems with iconv
        self::$isIconvEnabled = true;

        // Fail if iconv doesn't exist
        if (!function_exists('iconv')) {
            self::$isIconvEnabled = false;
        } elseif (!@iconv('UTF-8', 'UTF-16LE', 'x')) {
            // Sometimes iconv is not working, and e.g. iconv('UTF-8', 'UTF-16LE', 'x') just returns false,
            self::$isIconvEnabled = false;
        } elseif (defined('PHP_OS') && @stristr(PHP_OS, 'AIX') && defined('ICONV_IMPL') && (@strcasecmp(ICONV_IMPL, 'unknown') == 0) && defined('ICONV_VERSION') && (@strcasecmp(ICONV_VERSION, 'unknown') == 0)) {
            // CUSTOM: IBM AIX iconv() does not work
            self::$isIconvEnabled = false;
        }

        // Deactivate iconv default options if they fail (as seen on IMB i)
        if (self::$isIconvEnabled && !@iconv('UTF-8', 'UTF-16LE' . self::$iconvOptions, 'x')) {
            self::$iconvOptions = '';
        }

        return self::$isIconvEnabled;
    }

    private static function buildCharacterSets(): void
    {
        if (empty(self::$controlCharacters)) {
            self::buildControlCharacters();
        }

        if (empty(self::$SYLKCharacters)) {
            self::buildSYLKCharacters();
        }
    }

    /**
     * Convert from OpenXML escaped control character to PHP control character.
     *
     * Excel 2007 team:
     * ----------------
     * That's correct, control characters are stored directly in the shared-strings table.
     * We do encode characters that cannot be represented in XML using the following escape sequence:
     * _xHHHH_ where H represents a hexadecimal character in the character's value...
     * So you could end up with something like _x0008_ in a string (either in a cell value (<v>)
     * element or in the shared string <t> element.
     *
     * @param string $textValue Value to unescape
     *
     * @return string
     */
    public static function controlCharacterOOXML2PHP($textValue)
    {
        self::buildCharacterSets();

        return str_replace(array_keys(self::$controlCharacters), array_values(self::$controlCharacters), $textValue);
    }

    /**
     * Convert from PHP control character to OpenXML escaped control character.
     *
     * Excel 2007 team:
     * ----------------
     * That's correct, control characters are stored directly in the shared-strings table.
     * We do encode characters that cannot be represented in XML using the following escape sequence:
     * _xHHHH_ where H represents a hexadecimal character in the character's value...
     * So you could end up with something like _x0008_ in a string (either in a cell value (<v>)
     * element or in the shared string <t> element.
     *
     * @param string $textValue Value to escape
     *
     * @return string
     */
    public static function controlCharacterPHP2OOXML($textValue)
    {
        self::buildCharacterSets();

        return str_replace(array_values(self::$controlCharacters), array_keys(self::$controlCharacters), $textValue);
    }

    /**
     * Try to sanitize UTF8, replacing invalid sequences with Unicode substitution characters.
     */
    public static function sanitizeUTF8(string $textValue): string
    {
        $textValue = str_replace(["\xef\xbf\xbe", "\xef\xbf\xbf"], "\xef\xbf\xbd", $textValue);
        $subst = mb_substitute_character(); // default is question mark
        mb_substitute_character(65533); // Unicode substitution character
        // Phpstan does not think this can return false.
        $returnValue = mb_convert_encoding($textValue, 'UTF-8', 'UTF-8');
        mb_substitute_character(/** @scrutinizer ignore-type */ $subst);

        return self::returnString($returnValue);
    }

    /**
     * Strictly to satisfy Scrutinizer.
     *
     * @param mixed $value
     */
    private static function returnString($value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Check if a string contains UTF8 data.
     */
    public static function isUTF8(string $textValue): bool
    {
        return $textValue === self::sanitizeUTF8($textValue);
    }

    /**
     * Formats a numeric value as a string for output in various output writers forcing
     * point as decimal separator in case locale is other than English.
     *
     * @param float|int|string $numericValue
     */
    public static function formatNumber($numericValue): string
    {
        if (is_float($numericValue)) {
            return str_replace(',', '.', (string) $numericValue);
        }

        return (string) $numericValue;
    }

    /**
     * Converts a UTF-8 string into BIFF8 Unicode string data (8-bit string length)
     * Writes the string using uncompressed notation, no rich text, no Asian phonetics
     * If mbstring extension is not available, ASCII is assumed, and compressed notation is used
     * although this will give wrong results for non-ASCII strings
     * see OpenOffice.org's Documentation of the Microsoft Excel File Format, sect. 2.5.3.
     *
     * @param string $textValue UTF-8 encoded string
     * @param mixed[] $arrcRuns Details of rich text runs in $value
     */
    public static function UTF8toBIFF8UnicodeShort(string $textValue, array $arrcRuns = []): string
    {
        // character count
        $ln = self::countCharacters($textValue, 'UTF-8');
        // option flags
        if (empty($arrcRuns)) {
            $data = pack('CC', $ln, 0x0001);
            // characters
            $data .= self::convertEncoding($textValue, 'UTF-16LE', 'UTF-8');
        } else {
            $data = pack('vC', $ln, 0x09);
            $data .= pack('v', count($arrcRuns));
            // characters
            $data .= self::convertEncoding($textValue, 'UTF-16LE', 'UTF-8');
            foreach ($arrcRuns as $cRun) {
                $data .= pack('v', $cRun['strlen']);
                $data .= pack('v', $cRun['fontidx']);
            }
        }

        return $data;
    }

    /**
     * Converts a UTF-8 string into BIFF8 Unicode string data (16-bit string length)
     * Writes the string using uncompressed notation, no rich text, no Asian phonetics
     * If mbstring extension is not available, ASCII is assumed, and compressed notation is used
     * although this will give wrong results for non-ASCII strings
     * see OpenOffice.org's Documentation of the Microsoft Excel File Format, sect. 2.5.3.
     *
     * @param string $textValue UTF-8 encoded string
     */
    public static function UTF8toBIFF8UnicodeLong(string $textValue): string
    {
        // character count
        $ln = self::countCharacters($textValue, 'UTF-8');

        // characters
        $chars = self::convertEncoding($textValue, 'UTF-16LE', 'UTF-8');

        return pack('vC', $ln, 0x0001) . $chars;
    }

    /**
     * Convert string from one encoding to another.
     *
     * @param string $to Encoding to convert to, e.g. 'UTF-8'
     * @param string $from Encoding to convert from, e.g. 'UTF-16LE'
     */
    public static function convertEncoding(string $textValue, string $to, string $from): string
    {
        if (self::getIsIconvEnabled()) {
            $result = iconv($from, $to . self::$iconvOptions, $textValue);
            if (false !== $result) {
                return $result;
            }
        }

        return self::returnString(mb_convert_encoding($textValue, $to, $from));
    }

    /**
     * Get character count.
     *
     * @param string $encoding Encoding
     *
     * @return int Character count
     */
    public static function countCharacters(string $textValue, string $encoding = 'UTF-8'): int
    {
        return mb_strlen($textValue, $encoding);
    }

    /**
     * Get character count using mb_strwidth rather than mb_strlen.
     *
     * @param string $encoding Encoding
     *
     * @return int Character count
     */
    public static function countCharactersDbcs(string $textValue, string $encoding = 'UTF-8'): int
    {
        return mb_strwidth($textValue, $encoding);
    }

    /**
     * Get a substring of a UTF-8 encoded string.
     *
     * @param string $textValue UTF-8 encoded string
     * @param int $offset Start offset
     * @param ?int $length Maximum number of characters in substring
     */
    public static function substring(string $textValue, int $offset, ?int $length = 0): string
    {
        return mb_substr($textValue, $offset, $length, 'UTF-8');
    }

    /**
     * Convert a UTF-8 encoded string to upper case.
     *
     * @param string $textValue UTF-8 encoded string
     */
    public static function strToUpper(string $textValue): string
    {
        return mb_convert_case($textValue, MB_CASE_UPPER, 'UTF-8');
    }

    /**
     * Convert a UTF-8 encoded string to lower case.
     *
     * @param string $textValue UTF-8 encoded string
     */
    public static function strToLower(string $textValue): string
    {
        return mb_convert_case($textValue, MB_CASE_LOWER, 'UTF-8');
    }

    /**
     * Convert a UTF-8 encoded string to title/proper case
     * (uppercase every first character in each word, lower case all other characters).
     *
     * @param string $textValue UTF-8 encoded string
     */
    public static function strToTitle(string $textValue): string
    {
        return mb_convert_case($textValue, MB_CASE_TITLE, 'UTF-8');
    }

    public static function mbIsUpper(string $character): bool
    {
        return mb_strtolower($character, 'UTF-8') !== $character;
    }

    /**
     * Splits a UTF-8 string into an array of individual characters.
     */
    public static function mbStrSplit(string $string): array
    {
        // Split at all position not after the start: ^
        // and not before the end: $
        $split = preg_split('/(?<!^)(?!$)/u', $string);

        return ($split === false) ? [] : $split;
    }

    /**
     * Reverse the case of a string, so that all uppercase characters become lowercase
     * and all lowercase characters become uppercase.
     *
     * @param string $textValue UTF-8 encoded string
     */
    public static function strCaseReverse(string $textValue): string
    {
        $characters = self::mbStrSplit($textValue);
        foreach ($characters as &$character) {
            if (self::mbIsUpper($character)) {
                $character = mb_strtolower($character, 'UTF-8');
            } else {
                $character = mb_strtoupper($character, 'UTF-8');
            }
        }

        return implode('', $characters);
    }

    /**
     * Get the decimal separator. If it has not yet been set explicitly, try to obtain number
     * formatting information from locale.
     */
    public static function getDecimalSeparator(): string
    {
        if (!isset(self::$decimalSeparator)) {
            $localeconv = localeconv();
            self::$decimalSeparator = ($localeconv['decimal_point'] != '')
                ? $localeconv['decimal_point'] : $localeconv['mon_decimal_point'];

            if (self::$decimalSeparator == '') {
                // Default to .
                self::$decimalSeparator = '.';
            }
        }

        return self::$decimalSeparator;
    }

    /**
     * Set the decimal separator. Only used by NumberFormat::toFormattedString()
     * to format output by \PhpOffice\PhpSpreadsheet\Writer\Html and \PhpOffice\PhpSpreadsheet\Writer\Pdf.
     *
     * @param string $separator Character for decimal separator
     */
    public static function setDecimalSeparator(string $separator): void
    {
        self::$decimalSeparator = $separator;
    }

    /**
     * Get the thousands separator. If it has not yet been set explicitly, try to obtain number
     * formatting information from locale.
     */
    public static function getThousandsSeparator(): string
    {
        if (!isset(self::$thousandsSeparator)) {
            $localeconv = localeconv();
            self::$thousandsSeparator = ($localeconv['thousands_sep'] != '')
                ? $localeconv['thousands_sep'] : $localeconv['mon_thousands_sep'];

            if (self::$thousandsSeparator == '') {
                // Default to .
                self::$thousandsSeparator = ',';
            }
        }

        return self::$thousandsSeparator;
    }

    /**
     * Set the thousands separator. Only used by NumberFormat::toFormattedString()
     * to format output by \PhpOffice\PhpSpreadsheet\Writer\Html and \PhpOffice\PhpSpreadsheet\Writer\Pdf.
     *
     * @param string $separator Character for thousands separator
     */
    public static function setThousandsSeparator(string $separator): void
    {
        self::$thousandsSeparator = $separator;
    }

    /**
     *    Get the currency code. If it has not yet been set explicitly, try to obtain the
     *        symbol information from locale.
     */
    public static function getCurrencyCode(): string
    {
        if (!empty(self::$currencyCode)) {
            return self::$currencyCode;
        }
        self::$currencyCode = '$';
        $localeconv = localeconv();
        if (!empty($localeconv['currency_symbol'])) {
            self::$currencyCode = $localeconv['currency_symbol'];

            return self::$currencyCode;
        }
        if (!empty($localeconv['int_curr_symbol'])) {
            self::$currencyCode = $localeconv['int_curr_symbol'];

            return self::$currencyCode;
        }

        return self::$currencyCode;
    }

    /**
     * Set the currency code. Only used by NumberFormat::toFormattedString()
     *        to format output by \PhpOffice\PhpSpreadsheet\Writer\Html and \PhpOffice\PhpSpreadsheet\Writer\Pdf.
     *
     * @param string $currencyCode Character for currency code
     */
    public static function setCurrencyCode(string $currencyCode): void
    {
        self::$currencyCode = $currencyCode;
    }

    /**
     * Convert SYLK encoded string to UTF-8.
     *
     * @param string $textValue SYLK encoded string
     *
     * @return string UTF-8 encoded string
     */
    public static function SYLKtoUTF8(string $textValue): string
    {
        self::buildCharacterSets();

        // If there is no escape character in the string there is nothing to do
        if (strpos($textValue, '') === false) {
            return $textValue;
        }

        foreach (self::$SYLKCharacters as $k => $v) {
            $textValue = str_replace($k, $v, $textValue);
        }

        return $textValue;
    }

    /**
     * Retrieve any leading numeric part of a string, or return the full string if no leading numeric
     * (handles basic integer or float, but not exponent or non decimal).
     *
     * @param string $textValue
     *
     * @return mixed string or only the leading numeric part of the string
     */
    public static function testStringAsNumeric($textValue)
    {
        if (is_numeric($textValue)) {
            return $textValue;
        }
        $v = (float) $textValue;

        return (is_numeric(substr($textValue, 0, strlen((string) $v)))) ? $v : $textValue;
    }
}