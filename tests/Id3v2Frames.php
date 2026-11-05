<?php

/**
 * Representative, well-formed ID3v2 frame bodies, keyed by frame id, for each major version.
 * Used both to check normal parsing and as the starting point for truncation tests.
 */
final class Id3v2Frames
{
	/** @return array<string, string> frame id => frame body */
	public static function forVersion(int $major): array {
		$png = Id3v2Builder::png();
		if ($major == 2) {
			return array(
				'TT2' => "\x00Title Two",
				'TP1' => "\x01".Id3v2Builder::utf16('Artïst')."\x00\x00",
				'COM' => "\x00engDescription\x00Comment text",
				'ULT' => "\x00eng\x00Lyrics text",
				'PIC' => "\x00PNG\x03Cover\x00".$png,
				'TXX' => "\x00MyKey\x00MyValue",
				'WXX' => "\x00Link\x00http://example.com/",
				'WCM' => 'http://example.com/buy',
				'GEO' => "\x00application/octet-stream\x00file.bin\x00Object\x00DATA",
				'UFI' => "http://example.com/ufid\x00abcd-1234",
				'POP' => "user@example.com\x00\xC8\x00\x00\x00\x05",
				'CNT' => pack('N', 42),
				'SLT' => "\x00eng\x02\x01Desc\x00line one\x00".pack('N', 1000)."line two\x00".pack('N', 2000),
				'CRA' => "owner\x00\x00\x10\x00\x20data",
				'LNK' => "TT2http://example.com/link\x00extra",
			);
		}
		$frames = array(
			'TIT2' => "\x00Title Two",
			'TPE1' => "\x01".Id3v2Builder::utf16('Artïst')."\x00\x00",
			'TCON' => "\x00(17)Rock",
			'TRCK' => "\x003/12",
			'COMM' => "\x00engDescription\x00Comment text",
			'USLT' => "\x00eng\x00Lyrics text",
			'APIC' => "\x00image/png\x00\x03Cover\x00".$png,
			'TXXX' => "\x00MyKey\x00MyValue",
			'WXXX' => "\x00Link\x00http://example.com/",
			'WCOM' => 'http://example.com/buy',
			'GEOB' => "\x00application/octet-stream\x00file.bin\x00Object\x00DATA",
			'PRIV' => "owner.example\x00PRIVDATA",
			'UFID' => "http://example.com/ufid\x00abcd-1234",
			'POPM' => "user@example.com\x00\xC8\x00\x00\x00\x05",
			'PCNT' => pack('N', 42),
			'OWNE' => "\x00USD9.99\x0020240101Seller name",
			'COMR' => "\x00USD9.99\x0020251231http://shop.example/\x00\x01Seller\x00Description\x00image/png\x00".$png,
			'ENCR' => "owner\x00\x80encryption data",
			'GRID' => "owner\x00\x81group data",
			'LINK' => "TIT2http://example.com/link\x00extra",
			'SYLT' => "\x00eng\x02\x01Desc\x00line one\x00".pack('N', 1000)."line two\x00".pack('N', 2000),
			'USER' => "\x00engTerms of use",
			'AENC' => "owner\x00\x00\x10\x00\x20data",
			'ETCO' => "\x02\x01".pack('N', 100),
			'SYTC' => "\x02\x78".pack('N', 0),
			'POSS' => "\x02".pack('N', 500),
			'RBUF' => "\x00\x10\x00\x01\x00\x00\x00\x00",
			'MCDI' => "TOC DATA",
			'RVRB' => "\x00\x10\x00\x20\x01\x02\x03\x04\x05\x06\x07\x08",
		);
		if ($major == 3) {
			$frames['IPLS'] = "\x00producer\x00Someone\x00engineer\x00Someone Else\x00";
			$frames['RVAD'] = "\x03\x10".pack('n', 100).pack('n', 200).pack('n', 300).pack('n', 400);
			$frames['EQUA'] = "\x10".pack('n', 0x8000 | 1000).pack('n', 10);
		} else {
			$frames['RVA2'] = "track\x00\x01".pack('n', 512)."\x10\x7F\xFF";
			$frames['EQU2'] = "\x01Id\x00".pack('n', 1000).pack('n', 10);
			$frames['SEEK'] = pack('N', 1000);
			$frames['ASPI'] = pack('N', 0).pack('N', 1000).pack('n', 2)."\x08\x10\x20";
			$frames['SIGN'] = "\x81signature";
			$frames['CHAP'] = "chp1\x00".pack('N', 0).pack('N', 1000).pack('N', 0xFFFFFFFF).pack('N', 0xFFFFFFFF)
				.Id3v2Builder::frame(4, 'TIT2', "\x00Chapter one");
			$frames['CTOC'] = "toc\x00\x03\x01chp1\x00".Id3v2Builder::frame(4, 'TIT2', "\x00Contents");
		}
		return $frames;
	}
}
