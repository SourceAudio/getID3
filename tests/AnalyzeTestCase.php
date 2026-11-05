<?php

use PHPUnit\Framework\TestCase;

abstract class AnalyzeTestCase extends TestCase
{
	protected const FIXTURES = __DIR__.'/fixtures/';

	/** @var string[] */
	private array $tempFiles = array();

	protected function tearDown(): void {
		foreach ($this->tempFiles as $file) {
			@unlink($file);
		}
		$this->tempFiles = array();
	}

	protected function tempFile(string $contents, string $suffix = '.mp3'): string {
		$file = tempnam(sys_get_temp_dir(), 'getid3test').$suffix;
		file_put_contents($file, $contents);
		$this->tempFiles[] = $file;
		return $file;
	}

	/** MPEG audio frames with no tags, to put after a test ID3v2 tag. */
	protected static function mpegAudio(): string {
		static $audio = null;
		if ($audio === null) {
			$audio = file_get_contents(self::FIXTURES.'sine.mp3');
		}
		return $audio;
	}

	protected function analyze(string $filename, ?callable $configure = null): array {
		$getID3 = new getID3();
		if ($configure) {
			$configure($getID3);
		}
		$info = $getID3->analyze($filename);
		getid3_lib::CopyTagsToComments($info);
		return $info;
	}

	protected function analyzeBytes(string $bytes, string $suffix = '.mp3'): array {
		return $this->analyze($this->tempFile($bytes, $suffix));
	}

	/**
	 * Runs $callback and returns every PHP error/deprecation it raises as "[E_*] message @ file:line".
	 * Lets fuzz-style tests report all problems at once instead of stopping at the first.
	 *
	 * @param int $levels error levels to collect
	 *
	 * @return string[]
	 */
	protected static function collectIssues(callable $callback, int $levels = E_ALL): array {
		$issues = array();
		set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$issues) {
			if (!(error_reporting() & $errno)) {
				return true; // suppressed with @
			}
			$names = array(E_DEPRECATED => 'E_DEPRECATED', E_USER_DEPRECATED => 'E_USER_DEPRECATED', E_WARNING => 'E_WARNING', E_NOTICE => 'E_NOTICE');
			$issues[] = '['.($names[$errno] ?? $errno).'] '.$errstr.' @ '.basename($errfile).':'.$errline;
			return true;
		}, $levels);
		try {
			$callback();
		} finally {
			restore_error_handler();
		}
		return $issues;
	}
}
