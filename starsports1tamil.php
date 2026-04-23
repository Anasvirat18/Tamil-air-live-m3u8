<?php
set_time_limit(0);
ignore_user_abort(true);

$url = "https://anas-ts.onrender.com/box/9.ts";
$channel = "Starsports1tamil";

$segmentDuration = 10; // seconds
$maxSegments = 999999; // no fixed count limit now
$maxSizeBytes = 300 * 1024 * 1024; // 300 MB

$folder = __DIR__ . "/$channel";

if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

$segmentPrefix = "segment_";
$playlistFile = "$folder/playlist.m3u8";

$segmentIndex = 0;
$segmentList = [];

echo "🚀 300MB Limited Segmenter Starting...\n";

function getFolderSize($dir) {
    $size = 0;
    foreach (glob("$dir/*") as $file) {
        if (is_file($file)) {
            $size += filesize($file);
        }
    }
    return $size;
}

function cleanupOldSegments(&$segmentList, $folder, $maxSizeBytes) {
    while (getFolderSize($folder) > $maxSizeBytes && count($segmentList) > 0) {
        $old = array_shift($segmentList);
        $file = "$folder/$old";

        if (file_exists($file)) {
            unlink($file);
        }

        echo "🧹 Deleted old segment: $old (size limit reached)\n";
    }
}

while (true) {

    $segmentFile = "$folder/{$segmentPrefix}{$segmentIndex}.ts";
    echo "📦 Creating: $segmentFile\n";

    $read = @fopen($url, 'rb');
    $write = @fopen($segmentFile, 'wb');

    if (!$read || !$write) {
        echo "❌ Error opening stream. Retrying in 3s...\n";
        sleep(3);
        continue;
    }

    stream_set_timeout($read, 2);

    $start = microtime(true);
    $bytesWritten = 0;

    while ((microtime(true) - $start) < $segmentDuration) {

        if (feof($read)) break;

        $data = fread($read, 16384);

        if (!$data) {
            usleep(100000);
            continue;
        }

        fwrite($write, $data);
        $bytesWritten += strlen($data);
    }

    fclose($read);
    fclose($write);

    echo "✅ Segment saved ($bytesWritten bytes)\n";

    // Add segment
    $segmentList[] = "{$segmentPrefix}{$segmentIndex}.ts";

    // 🔥 ENFORCE 300MB LIMIT
    cleanupOldSegments($segmentList, $folder, $maxSizeBytes);

    // Build playlist
    $m3u8 = "#EXTM3U\n";
    $m3u8 .= "#EXT-X-VERSION:3\n";
    $m3u8 .= "#EXT-X-TARGETDURATION:$segmentDuration\n";

    $mediaSequence = max(0, $segmentIndex - count($segmentList) + 1);
    $m3u8 .= "#EXT-X-MEDIA-SEQUENCE:$mediaSequence\n";

    foreach ($segmentList as $seg) {
        $m3u8 .= "#EXTINF:$segmentDuration,\n$seg\n";
    }

    file_put_contents($playlistFile, $m3u8);

    $segmentIndex++;
}
