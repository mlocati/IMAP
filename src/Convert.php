<?php

namespace MLocati\IMAP;

/**
 * Conversion-related stuff.
 */
class Convert
{
    /**
     * Convert a MIME-encoded text to UTF-8.
     *
     * @param string $string
     *
     * @return string
     */
    public static function mimeEncodedToUTF8($string)
    {
        $result = '';
        if (is_string($string)) {
            $result = $string;
            if ($string !== '') {
                $v = @imap_utf8($string);
                if (is_string($v) && $v !== '') {
                    $result = $v;
                }
            }
        }

        return $result;
    }

    /**
     * Decodes a text from a specific character set to utf-8.
     *
     * @param string $fromCharset The source character set
     * @param string $text The string to be decoded
     *
     * @return string|false
     */
    public static function charsetToUtf8($fromCharset, $text)
    {
        if (!is_string($text)) {
            return false;
        }
        if ($text === '') {
            return $text;
        }
        $fromCharset = is_string($fromCharset) ? strtoupper(trim($fromCharset)) : '';
        if ($fromCharset === '') {
            return $text;
        }
        switch (str_replace('-', '', $fromCharset)) {
            case 'UTF8':
            case 'ANSI':
            case 'USANSI':
            case 'ASCII':
            case 'USASCII':
                return $text;
        }
        if (function_exists('utf8_encode')) {
            switch (str_replace('-', '', $fromCharset)) {
                case 'ISO88591':
                case 'ISOIR100':
                case 'LATIN1':
                case 'L1':
                case 'CSISOLATIN1':
                case 'IBM819':
                case 'CP819':
                    $decoded = @utf8_encode($text);
                    if (is_string($decoded) && $decoded !== '') {
                        return $decoded;
                    }
                    break;
            }
        }
        if (function_exists('iconv')) {
            $decoded = @iconv($fromCharset, 'UTF-8', $text);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }
        if (function_exists('mb_convert_encoding')) {
            $decoded = @mb_convert_encoding($text, 'UTF-8', $fromCharset);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }
        $match = preg_match('/^CP-(.+)$/i', $fromCharset);
        if ($match) {
            $decoded = static::charsetToUtf8('CP'.$match[1], $text);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return false;
    }

    /**
     * Convert to string some data represented in a particular encoding.
     *
     * @param int $encoding One of the IMAP constants ENC7BIT, ENC8BIT, ENCBINARY, ENCBASE64, ENCQUOTEDPRINTABLE, ...
     * @param string $data
     *
     * @return string|false
     */
    public static function decodeEncoding($encoding, $data)
    {
        if (!is_string($data)) {
            return false;
        }
        if ($data === '') {
            return $data;
        }
        switch ($encoding) {
            case ENCBASE64:
                if (function_exists('base64_decode')) {
                    $decoded = @base64_decode($data);
                    if (is_string($decoded) && $decoded !== '') {
                        return $decoded;
                    }
                }
                break;
            case ENCQUOTEDPRINTABLE:
                if (function_exists('quoted_printable_decode')) {
                    $decoded = @quoted_printable_decode($data);
                    if (is_string($decoded) && $decoded !== '') {
                        return $decoded;
                    }
                }
                break;
            case ENC7BIT:
            case ENC8BIT:
            case ENCBINARY:
            case ENCOTHER:
            default:
                return $data;
                break;
        }

        return false;
    }
}
