<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Helpers in getid3_lib that were added or changed for PHP 8.5 compatibility.
 */
final class LibTest extends TestCase
{
	public function testOrdReturnsZeroForEmptyString(): void {
		// ord('') is deprecated in PHP 8.5; it used to return 0 and parsers rely on that for truncated data
		$this->assertSame(0, getid3_lib::ord(''));
	}

	public function testOrdReturnsFirstByteOfLongerString(): void {
		// ord('abc') is deprecated in PHP 8.5; it used to return the value of the first byte
		$this->assertSame(0xFF, getid3_lib::ord("\xFFabc"));
		$this->assertSame(0, getid3_lib::ord("\x00\x01"));
		$this->assertSame(ord('i'), getid3_lib::ord('image/jpeg'));
	}

	public function testOrdMatchesNativeOrdForEverySingleByte(): void {
		for ($i = 0; $i < 256; $i++) {
			$this->assertSame($i, getid3_lib::ord(chr($i)));
		}
	}

	public function testStrposSafeReturnsFalseWhenOffsetIsOutsideHaystack(): void {
		// strpos() throws a ValueError in PHP 8 when the offset is past the end of the haystack
		$this->assertFalse(getid3_lib::strpos_safe('abc', "\x00", 4));
		$this->assertFalse(getid3_lib::strpos_safe('', "\x00", 1));
		$this->assertFalse(getid3_lib::strpos_safe('abc', 'a', -4));
	}

	public function testStrposSafeBehavesLikeStrposOtherwise(): void {
		$this->assertSame(3, getid3_lib::strpos_safe("abc\x00def\x00", "\x00", 0));
		$this->assertSame(7, getid3_lib::strpos_safe("abc\x00def\x00", "\x00", 4));
		$this->assertFalse(getid3_lib::strpos_safe('abc', "\x00", 3));
		$this->assertSame(2, getid3_lib::strpos_safe('abc', 'c', -1));
	}

	public function testCastAsIntLeavesOutOfRangeFloatsAsFloats(): void {
		// PHP 8.5 warns "The float ... is not representable as an int" when casting these
		$this->assertSame(2.4009180673865043E+37, getid3_lib::CastAsInt(2.4009180673865043E+37));
		$this->assertSame((float) PHP_INT_MAX, getid3_lib::CastAsInt((float) PHP_INT_MAX));
		$this->assertSame(-1.0E+30, getid3_lib::CastAsInt(-1.0E+30));
		$this->assertSame(INF, getid3_lib::CastAsInt(INF));
		$this->assertNan(getid3_lib::CastAsInt(NAN));
	}

	public function testCastAsIntConvertsWholeNumbersInRange(): void {
		$this->assertSame(5, getid3_lib::CastAsInt(5.0));
		$this->assertSame(-5, getid3_lib::CastAsInt('-5'));
		$this->assertSame(1.5, getid3_lib::CastAsInt(1.5));
		$this->assertSame(4294967296, getid3_lib::CastAsInt(4294967296.0));
	}

	public function testTruncDoesNotOverflow(): void {
		$this->assertSame(3, getid3_lib::trunc(3.9));
		$this->assertSame(-3, getid3_lib::trunc(-3.9));
		$this->assertSame(1.0E+20, getid3_lib::trunc(1.0E+20));
	}

	public static function codePoints(): array {
		$points = array();
		foreach (array(0x00, 0x41, 0x7F, 0x80, 0xE9, 0xFF, 0x7FF, 0x800, 0x20AC, 0xFFFD, 0xFFFF, 0x10000, 0x1F3B5, 0x10FFFF) as $cp) {
			$points[sprintf('U+%04X', $cp)] = array($cp);
		}
		return $points;
	}

	#[DataProvider('codePoints')]
	public function testIconvFallbackIntUtf8EncodesCodePoint(int $codepoint): void {
		// 3- and 4-byte sequences used to be built with out-of-range chr() values (deprecated in PHP 8.5)
		$this->assertSame(bin2hex(mb_chr($codepoint, 'UTF-8')), bin2hex(getid3_lib::iconv_fallback_int_utf8($codepoint)));
	}

	public function testIconvFallbackIso88591Utf8(): void {
		$latin1 = "Caf\xE9 \xFF";
		$this->assertSame('Café ÿ', getid3_lib::iconv_fallback_iso88591_utf8($latin1));
		$this->assertSame("\xEF\xBB\xBFCafé ÿ", getid3_lib::iconv_fallback_iso88591_utf8($latin1, true));
	}

	public function testIconvFallbackUtf8Iso88591(): void {
		$this->assertSame("Caf\xE9 ?", getid3_lib::iconv_fallback_utf8_iso88591('Café €'));
	}

	public function testIconvFallbackUtf8ToUtf16(): void {
		$text = 'Aé€ 日本';
		$this->assertSame(bin2hex(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), bin2hex(getid3_lib::iconv_fallback_utf8_utf16be($text)));
		$this->assertSame(bin2hex(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), bin2hex(getid3_lib::iconv_fallback_utf8_utf16le($text)));
		$this->assertSame(bin2hex("\xFF\xFE".mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), bin2hex(getid3_lib::iconv_fallback_utf8_utf16($text)));
	}

	public function testIconvFallbackUtf8DecodersSkipTruncatedSequences(): void {
		// a multi-byte sequence cut off at the end of the string must not read past the end
		$this->assertSame(bin2hex("\x00A"), bin2hex(getid3_lib::iconv_fallback_utf8_utf16be("A\xE2\x82")));
		$this->assertSame(bin2hex("A\x00"), bin2hex(getid3_lib::iconv_fallback_utf8_utf16le("A\xF0\x9F")));
	}
}
