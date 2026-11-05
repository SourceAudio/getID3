<?php

/**
 * The backtick operator is deprecated in PHP 8.5 and was replaced by shell_exec(). These check that
 * shell commands still work and still escape file names (no shell injection).
 */
final class ShellCommandTest extends AnalyzeTestCase
{
	private string $dir;
	private string $cwd;

	protected function setUp(): void {
		if (GETID3_OS_ISWINDOWS || !function_exists('shell_exec')) {
			$this->markTestSkipped('Requires a Unix shell');
		}
		$this->dir = sys_get_temp_dir().'/getid3-shell-'.bin2hex(random_bytes(4));
		mkdir($this->dir);
		// an injected command would run here, creating $this->dir/pwned
		$this->cwd = getcwd();
		chdir($this->dir);
	}

	protected function tearDown(): void {
		parent::tearDown();
		if (isset($this->cwd)) {
			chdir($this->cwd);
		}
		if (isset($this->dir) && is_dir($this->dir)) {
			foreach (array_diff(scandir($this->dir), array('.', '..')) as $entry) {
				unlink($this->dir.'/'.$entry);
			}
			rmdir($this->dir);
		}
	}

	/** A file name full of shell metacharacters that would create "pwned" if it were not escaped. */
	private function hostileFilename(): string {
		return $this->dir.'/it\'s "a" $(touch pwned) `touch pwned`; touch pwned | touch pwned & touch pwned.mp3';
	}

	public function testHashDataOfTaggedFile(): void {
		$file = $this->hostileFilename();
		copy(self::FIXTURES.'sine-id3v24.mp3', $file);

		$info = $this->analyze($file, function (getID3 $getID3) {
			$getID3->option_md5_data  = true;
			$getID3->option_sha1_data = true;
		});

		// a tagged file is hashed with "head | tail | md5sum", run through shell_exec()
		$this->assertGreaterThan(0, $info['avdataoffset']);
		$audio = substr(file_get_contents($file), $info['avdataoffset'], $info['avdataend'] - $info['avdataoffset']);
		$this->assertSame(md5($audio), $info['md5_data']);
		$this->assertSame(sha1($audio), $info['sha1_data']);
		$this->assertFileDoesNotExist($this->dir.'/pwned');
	}

	public function testHashDataMatchesPhpImplementation(): void {
		$file = $this->hostileFilename();
		copy(self::FIXTURES.'sine.wav', $file);
		$data = file_get_contents($file);

		$this->assertSame(md5(substr($data, 44, 1000)), getid3_lib::hash_data($file, 44, 1044, 'md5'));
		$this->assertSame(sha1(substr($data, 44, 1000)), getid3_lib::hash_data($file, 44, 1044, 'sha1'));
		$this->assertFileDoesNotExist($this->dir.'/pwned');
	}

	public function testGetFileSizeSyscall(): void {
		$file = $this->hostileFilename();
		copy(self::FIXTURES.'sine.flac', $file);

		$this->assertSame((float) filesize($file), getid3_lib::getFileSizeSyscall($file));
		$this->assertFileDoesNotExist($this->dir.'/pwned');
	}

	public function testGetFileSizeSyscallMissingFile(): void {
		$this->assertFalse(@getid3_lib::getFileSizeSyscall($this->dir.'/missing'));
	}
}
