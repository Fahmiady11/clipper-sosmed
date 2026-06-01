<?php

namespace App\Services\Transcribers;

class WhisperTranscriber
{
    public function __construct(
        private string $ffmpeg,
        private string $jobId
    ) {}

    public function transcribe(string $cutFileArg, string $destSrt, string $lang): void
    {
        echo "Using Whisper for transcription\n";
        $whisperCli = $this->findBinary('whisper-cli');
        $model      = $this->findWhisperModel();
        $audioFile  = "/tmp/{$this->jobId}_audio.wav";
        $outPrefix  = "/tmp/{$this->jobId}_audio";
        $whisperSrt = "$outPrefix.srt";

        $audioOut = escapeshellarg($audioFile);
        exec("{$this->ffmpeg} -i $cutFileArg -ar 16000 -ac 1 -f wav $audioOut -y 2>&1", $o, $c);
        if ($c !== 0) {
            throw new \RuntimeException('FFmpeg audio extract failed: ' . implode("\n", $o));
        }

        $langFlag  = !in_array($lang, ['auto', '']) ? "-l $lang" : '';
        $modelArg  = escapeshellarg($model);
        $audioArg  = escapeshellarg($audioFile);
        $prefixArg = escapeshellarg($outPrefix);

        exec("$whisperCli -m $modelArg -f $audioArg $langFlag --output-srt -of $prefixArg 2>&1", $wo, $wc);
        @unlink($audioFile);

        if ($wc !== 0 || !file_exists($whisperSrt)) {
            throw new \RuntimeException('Whisper failed: ' . implode("\n", $wo));
        }

        rename($whisperSrt, $destSrt);
    }

    private function findBinary(string $name): string
    {
        $paths = ["/usr/local/bin/$name", "/opt/homebrew/bin/$name", "/usr/bin/$name"];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return $name;
    }

    private function findWhisperModel(): string
    {
        $candidates = [
            '/opt/homebrew/share/whisper-cpp/ggml-base.bin',
            '/usr/local/share/whisper-cpp/ggml-base.bin',
            '/opt/homebrew/share/whisper-cpp/ggml-small.bin',
            '/opt/homebrew/share/whisper-cpp/ggml-tiny.bin',
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        throw new \RuntimeException('Whisper model not found. Download ggml-base.bin to /opt/homebrew/share/whisper-cpp/');
    }
}
