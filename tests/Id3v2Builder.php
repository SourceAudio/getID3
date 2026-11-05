<?php

/**
 * Builds raw ID3v2 tags so tests can feed the parser exact (including malformed) bytes.
 */
final class Id3v2Builder
{
	public static function synchsafe(int $n): string {
		return chr(($n >> 21) & 0x7F).chr(($n >> 14) & 0x7F).chr(($n >> 7) & 0x7F).chr($n & 0x7F);
	}

	/**
	 * @param int    $major 2, 3 or 4
	 * @param string $id    frame id (3 chars for v2.2, 4 chars otherwise)
	 * @param string $data  frame body
	 */
	public static function frame(int $major, string $id, string $data): string {
		$length = strlen($data);
		switch ($major) {
			case 2:
				return $id.substr(pack('N', $length), 1).$data;
			case 3:
				return $id.pack('N', $length)."\x00\x00".$data;
			case 4:
				return $id.self::synchsafe($length)."\x00\x00".$data;
		}
		throw new InvalidArgumentException('Unsupported ID3v2 version '.$major);
	}

	/**
	 * @param int      $major  2, 3 or 4
	 * @param string[] $frames frames built with frame()
	 */
	public static function tag(int $major, array $frames, int $padding = 32): string {
		$body = implode('', $frames).str_repeat("\x00", $padding);
		return 'ID3'.chr($major)."\x00\x00".self::synchsafe(strlen($body)).$body;
	}

	/** UTF-16 with little-endian BOM, as written by most taggers for encoding $01 */
	public static function utf16(string $utf8): string {
		return "\xFF\xFE".mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
	}

	/** A tiny but valid PNG image (1x1 pixel) */
	public static function png(): string {
		return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
	}
}
