<?php

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ID3v2 parsing of well-formed, malformed and truncated tags.
 *
 * PHPUnit is configured to fail on any deprecation, notice or warning, so every test here also
 * checks that parsing raises none (e.g. PHP 8.5's ord() deprecations).
 */
final class Id3v2ParsingTest extends AnalyzeTestCase
{
	public static function versions(): array {
		return array('ID3v2.2' => array(2), 'ID3v2.3' => array(3), 'ID3v2.4' => array(4));
	}

	private function analyzeTag(int $major, array $frames): array {
		$built = array();
		foreach ($frames as $id => $data) {
			$built[] = Id3v2Builder::frame($major, $id, $data);
		}
		return $this->analyzeBytes(Id3v2Builder::tag($major, $built).self::mpegAudio());
	}

	private static function titleFrame(int $major): string {
		return ($major == 2) ? 'TT2' : 'TIT2';
	}

	#[DataProvider('versions')]
	public function testWellFormedTag(int $major): void {
		$info = $this->analyzeTag($major, Id3v2Frames::forVersion($major));

		$this->assertArrayNotHasKey('error', $info);
		$this->assertSame('mp3', $info['fileformat']);
		$this->assertSame($major, $info['id3v2']['majorversion']);
		$this->assertSame(22050, $info['audio']['sample_rate']);

		$comments = $info['comments'];
		$this->assertSame(array('Title Two'), $comments['title']);
		$this->assertSame(array('Artïst'), $comments['artist']);
		$this->assertSame(array('Description' => 'Comment text'), $comments['comment']);
		$this->assertSame(array('MyKey' => 'MyValue'), $comments['text']);
		$this->assertSame(array('http://example.com/'), $comments['url_user']);
		$this->assertSame(array('http://example.com/buy'), $comments['commercial_information']);
		$this->assertSame(array('http://example.com/link'), $comments['linked_information']);

		$this->assertCount(1, $comments['picture']);
		$this->assertSame(Id3v2Builder::png(), $comments['picture'][0]['data']);
		$this->assertSame('image/png', $comments['picture'][0]['image_mime']);
	}

	#[DataProvider('versions')]
	public function testWellFormedBinaryFrames(int $major): void {
		$info = $this->analyzeTag($major, Id3v2Frames::forVersion($major));
		$id3v2 = $info['id3v2'];

		$pop = ($major == 2) ? 'POP' : 'POPM';
		$this->assertSame('user@example.com', $id3v2[$pop][0]['email']);
		$this->assertSame(200, $id3v2[$pop][0]['rating']);
		$this->assertSame(5, $id3v2[$pop][0]['counter']);

		$ufid = ($major == 2) ? 'UFI' : 'UFID';
		$this->assertSame('http://example.com/ufid', $id3v2[$ufid][0]['ownerid']);
		$this->assertSame('abcd-1234', $id3v2[$ufid][0]['data']);

		$geob = ($major == 2) ? 'GEO' : 'GEOB';
		$this->assertSame('application/octet-stream', $id3v2[$geob][0]['mime']);
		$this->assertSame('file.bin', $id3v2[$geob][0]['filename']);
		$this->assertSame('Object', $id3v2[$geob][0]['description']);
		$this->assertSame('DATA', $id3v2[$geob][0]['objectdata']);

		if ($major == 2) {
			$this->assertSame('image/png', $id3v2['PIC'][0]['image_mime']);
			return;
		}

		$this->assertSame('owner.example', $id3v2['PRIV'][0]['ownerid']);
		$this->assertSame('PRIVDATA', $id3v2['PRIV'][0]['data']);
		$this->assertSame(128, $id3v2['ENCR'][0]['methodsymbol']);
		$this->assertSame(129, $id3v2['GRID'][0]['groupsymbol']);
		$this->assertSame('9.99', $id3v2['OWNE'][0]['pricepaid']['value']);
		$this->assertSame('Seller name', $id3v2['OWNE'][0]['seller']);
		$this->assertSame('http://shop.example/', $id3v2['COMR'][0]['contacturl']);
		$this->assertSame('Seller', $id3v2['COMR'][0]['sellername']);
		$this->assertSame('Description', $id3v2['COMR'][0]['description']);
		$this->assertSame('image/png', $id3v2['COMR'][0]['mime']);
		$this->assertSame(Id3v2Builder::png(), $id3v2['COMR'][0]['logo']);
		$this->assertSame(42, $id3v2['PCNT'][0]['data']);
	}

	public function testEqualisationFrameIsParsed(): void {
		// EQUA used to divide a string by 8, which is a TypeError in PHP 8
		$info = $this->analyzeTag(3, array('EQUA' => Id3v2Frames::forVersion(3)['EQUA']));
		$this->assertSame(16, $info['id3v2']['EQUA'][0]['adjustmentbits']);
		$this->assertSame(array('incdec' => true, 'adjustment' => 10), $info['id3v2']['EQUA'][0][1000]);
	}

	public function testEqualisation2FrameIsParsed(): void {
		// EQU2 used to write its adjustment points into string offsets of the raw frame data
		$info = $this->analyzeTag(4, array('EQU2' => "\x01Id\x00".pack('n', 2000).pack('n', 10).pack('n', 4001).pack('n', 512)));
		$this->assertSame('Id', $info['id3v2']['EQU2'][0]['description']);
		$this->assertSame(array(
			array('frequency' => 1000, 'adjustment' => 10),
			array('frequency' => 2000.5, 'adjustment' => 512),
		), $info['id3v2']['EQU2'][0]['adjustments']);
	}

	public static function pictureMimeTypes(): array {
		return array(
			'ID3v2.3 image/jpeg' => array(3, 'image/jpeg'),
			'ID3v2.4 image/jpeg' => array(4, 'image/jpeg'),
			'ID3v2.3 image/png'  => array(3, 'image/png'),
			'ID3v2.4 empty mime' => array(4, ''),
			'ID3v2.3 empty mime' => array(3, ''),
		);
	}

	/**
	 * module.tag.id3v2.php:1396 (APIC mime type) called ord() on the whole mime type string, which
	 * PHP 8.5 deprecates ("Providing a string that is not one byte long").
	 */
	#[DataProvider('pictureMimeTypes')]
	public function testAttachedPictureMimeType(int $major, string $mime): void {
		$info = $this->analyzeTag($major, array('APIC' => "\x00".$mime."\x00\x03Cover\x00".Id3v2Builder::png()));

		$apic = $info['id3v2']['APIC'][0];
		$this->assertSame($mime, $apic['mime']);
		$this->assertSame(3, $apic['picturetypeid']);
		$this->assertSame('Cover', $apic['description']);
		$this->assertSame(Id3v2Builder::png(), $apic['data']);
	}

	/**
	 * module.tag.id3v2.php:995 (COMM) called ord() on the byte after the description terminator,
	 * which is an empty string when the frame ends there; PHP 8.5 deprecates ord('').
	 */
	#[DataProvider('versions')]
	public function testCommentFrameEndingAtDescriptionTerminator(int $major): void {
		$comm = ($major == 2) ? 'COM' : 'COMM';
		$info = $this->analyzeTag($major, array(
			$comm                        => "\x00engDescription\x00",
			self::titleFrame($major)     => "\x00After",
		));
		$this->assertSame('Description', $info['id3v2'][$comm][0]['description']);
		$this->assertSame('', $info['id3v2'][$comm][0]['data']);
		$this->assertSame(array('After'), $info['comments']['title']);
	}

	#[DataProvider('versions')]
	public function testUtf16CommentFrame(int $major): void {
		$comm = ($major == 2) ? 'COM' : 'COMM';
		$info = $this->analyzeTag($major, array($comm => "\x01eng".Id3v2Builder::utf16('Desc')."\x00\x00".Id3v2Builder::utf16('Commént')));
		// (the comment's key is the description's raw UTF-16 bytes - long-standing behaviour, not checked here)
		$this->assertSame(array('Commént'), array_values($info['comments']['comment']));
	}

	#[DataProvider('versions')]
	public function testInvalidTextEncodingByteIsReportedAndTolerated(int $major): void {
		$comm = ($major == 2) ? 'COM' : 'COMM';
		$info = $this->analyzeTag($major, array(
			$comm                    => "\x07engDescription\x00Text",
			self::titleFrame($major) => "\x00After",
		));
		$this->assertStringContainsString('Invalid text encoding byte (7)', implode("\n", $info['warning']));
		$this->assertSame(array('After'), $info['comments']['title']);
	}

	public static function framesToTruncate(): array {
		$cases = array();
		foreach (array(2, 3, 4) as $major) {
			foreach (Id3v2Frames::forVersion($major) as $id => $data) {
				$cases['ID3v2.'.$major.' '.$id] = array($major, $id, $data);
			}
		}
		return $cases;
	}

	/**
	 * Every prefix of every frame type, as written by a buggy tagger: the frame header claims exactly
	 * the bytes present, and parsing must carry on to the next frame without errors or deprecations.
	 */
	#[DataProvider('framesToTruncate')]
	public function testTruncatedFrameBodies(int $major, string $id, string $data): void {
		$title = self::titleFrame($major);
		for ($length = 0; $length <= strlen($data); $length++) {
			$info = array();
			$issues = self::collectIssues(function () use ($major, $id, $title, $data, $length, &$info) {
				$info = $this->analyzeBytes(Id3v2Builder::tag($major, array(
					Id3v2Builder::frame($major, $id, substr($data, 0, $length)),
					Id3v2Builder::frame($major, $title, "\x00After"),
				)).self::mpegAudio());
			});
			$this->assertSame(array(), $issues, $id.' truncated to '.$length.' bytes');
			$this->assertSame('mp3', $info['fileformat'], $id.' truncated to '.$length.' bytes');
			if ($id !== 'CHAP' && $id !== 'CTOC') { // sub-frames of a truncated chapter may override the title
				$this->assertSame(array('After'), $info['comments']['title'], $id.' truncated to '.$length.' bytes');
			}
		}
	}

	/**
	 * A complete tag cut off at every byte (an interrupted upload or download).
	 */
	#[DataProvider('versions')]
	public function testTruncatedFile(int $major): void {
		$frames = array();
		foreach (Id3v2Frames::forVersion($major) as $id => $data) {
			$frames[] = Id3v2Builder::frame($major, $id, $data);
		}
		$file = Id3v2Builder::tag($major, $frames).self::mpegAudio();

		$problems = array();
		for ($length = 0; $length < strlen($file); $length += ($length < 1024 ? 1 : 61)) {
			$issues = self::collectIssues(function () use ($file, $length) {
				$this->analyzeBytes(substr($file, 0, $length));
			});
			foreach ($issues as $issue) {
				$problems[$issue] = 'first seen at length '.$length;
			}
		}
		$this->assertSame(array(), $problems);
	}
}
