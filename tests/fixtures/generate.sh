#!/bin/sh
# Regenerates the small audio fixtures used by the test suite. Requires ffmpeg.
# The generated files are committed, so this only needs to be re-run to add or change fixtures.
set -e
cd "$(dirname "$0")"

FF="ffmpeg -hide_banner -loglevel error -y -bitexact"
TONE="-f lavfi -i sine=frequency=440:duration=0.5:sample_rate=22050"
META="-metadata title=TestTitle -metadata artist=TestArtist -metadata album=TestAlbum -metadata date=2024 -metadata track=3/12 -metadata genre=Rock -metadata comment=A_comment"

# 16x16 PNG used as cover art
$FF -f lavfi -i color=c=red:s=16x16 -frames:v 1 cover.png

$FF $TONE -i cover.png -map 0:a -map 1:v -c:a libmp3lame -b:a 32k -ac 1 -c:v copy \
	-id3v2_version 3 -metadata:s:v title="Album cover" -metadata:s:v comment="Cover (front)" \
	$META sine-id3v23-apic.mp3
$FF $TONE -c:a libmp3lame -b:a 32k -ac 1 -id3v2_version 4 $META sine-id3v24.mp3
# no tags at all: tests prepend/append their own
$FF $TONE -c:a libmp3lame -b:a 32k -ac 1 -id3v2_version 0 -write_id3v1 0 -write_xing 0 -map_metadata -1 sine.mp3
$FF $TONE -c:a flac $META sine.flac
$FF $TONE -c:a libvorbis -ac 1 -b:a 32k $META sine.ogg
$FF $TONE -c:a aac -b:a 32k -ac 1 $META sine.m4a
$FF $TONE -c:a pcm_s16le $META sine.wav
$FF $TONE -c:a pcm_s16be $META sine.aiff
rm -f cover.png
