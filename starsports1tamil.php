<?php
set_time_limit(0);
ignore_user_abort(true);

$url = "https://ts-1dho.onrender.com/box.ts?id=4";
$channel = "Starsports1tamil";

$segmentDuration = 10; // seconds
$maxSegments = 6;
$folder = __DIR__ . "/$channel";

if (!is_dir($folder)) mkdir($folder, 0777, true);

$segmentPrefix = "segment_";
$playlistFile = "$folder/playlist.m3u8";

$segmentIndex = 0;
$segmentList = [];

echo "🚀 Faster segmenter starting...\n";

while (true) {
    $segmentFile = "$folder/{$segmentPrefix}{$segmentIndex}.ts";
    echo "📦 Creating: $segmentFile\n";

    $read = fopen($url, 'r');
    $write = fopen($segmentFile, 'w');

    if (!$read || !$write) {
        echo "❌ Error opening stream. Retrying in 3s...\n";
        sleep(3);
        continue;
    }

    // ⚡ Make reading faster
    stream_set_timeout($read, 2);
    stream_set_blocking($read, true);

    $start = microtime(true);
    $bytesWritten = 0;

    while ((microtime(true) - $start) < $segmentDuration) {
        $data = fread($read, 16384); // 16 KB chunk
        if (!$data) break;
        $bytesWritten += strlen($data);
        fwrite($write, $data);
    }

    fclose($read);
    fclose($write);

    echo "✅ Segment saved ($bytesWritten bytes)\n";

    // Maintain segment list
    $segmentList[] = "{$segmentPrefix}{$segmentIndex}.ts";
    if (count($segmentList) > $maxSegments) {
        $old = array_shift($segmentList);
        @unlink("$folder/$old");
    }

    // Write M3U8 playlist
    $m3u8 = "#EXTM3U\n";
    $m3u8 .= "#EXT-X-VERSION:3\n";
    $m3u8 .= "#EXT-X-TARGETDURATION:$segmentDuration\n";
    $m3u8 .= "#EXT-X-MEDIA-SEQUENCE:" . ($segmentIndex - count($segmentList) + 1) . "\n";
    foreach ($segmentList as $seg) {
        $m3u8 .= "#EXTINF:$segmentDuration,\n$seg\n";
    }

    file_put_contents($playlistFile, $m3u8);

    $segmentIndex++;
}
