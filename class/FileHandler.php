<?php

class FileHandler
{
	private $config = [];

	// Estensioni riconosciute => tipo MIME
	const VIDEO_TYPES = [
		'mp4'  => 'video/mp4',
		'mkv'  => 'video/x-matroska',
		'webm' => 'video/webm',
		'avi'  => 'video/x-msvideo',
		'mov'  => 'video/quicktime',
		'flv'  => 'video/x-flv',
	];
	const MUSIC_TYPES = [
		'mp3'  => 'audio/mpeg',
		'm4a'  => 'audio/mp4',
		'opus' => 'audio/ogg',
		'ogg'  => 'audio/ogg',
		'flac' => 'audio/flac',
		'wav'  => 'audio/wav',
	];

	public function __construct()
	{
		$this->config = require dirname(__DIR__).'/config/config.php';
	}

	public function listVideos()
	{
		return $this->list_files(array_keys(self::VIDEO_TYPES));
	}

	public function listMusics()
	{
		return $this->list_files(array_keys(self::MUSIC_TYPES));
	}

	// Elenco dei file, comprese le sottocartelle (es. cartelle delle playlist).
	// "name" è il percorso relativo alla cartella dei download.
	private function list_files($exts)
	{
		if(!$this->outuput_folder_exists())
			return [];

		$folder = rtrim($this->get_downloads_folder(), '/');
		$files = [];

		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
		);

		foreach($it as $f)
		{
			if(!$f->isFile())
				continue;

			if(!in_array(strtolower($f->getExtension()), $exts, true))
				continue;

			// File intermedi di yt-dlp durante il download (es. "titolo.f137.mp4")
			if(preg_match('/\.f\d+\.[a-z0-9]+$/i', $f->getFilename()))
				continue;

			$files[] = [
				"name" => substr($f->getPathname(), strlen($folder) + 1),
				"size" => $this->to_human_filesize($f->getSize()),
			];
		}

		usort($files, function($a, $b) { return strnatcasecmp($a["name"], $b["name"]); });

		return $files;
	}

	// Restituisce il percorso completo di un file della cartella download,
	// oppure null se non esiste, non ha un'estensione ammessa o sta fuori dalla cartella.
	public function resolve($name, $type = null)
	{
		if(!is_string($name) || $name === '' || strpos($name, "\0") !== false)
			return null;

		$base = realpath($this->get_downloads_folder());
		$path = realpath($base.'/'.$name);

		if($base === false || $path === false || !is_file($path))
			return null;

		if(strpos($path, $base.'/') !== 0)
			return null; // tentativo di uscire dalla cartella (../)

		if($this->mime_type($path, $type) === null)
			return null;

		return $path;
	}

	public function mime_type($path, $type = null)
	{
		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		if($type === 'v')
			$types = self::VIDEO_TYPES;
		elseif($type === 'm')
			$types = self::MUSIC_TYPES;
		else
			$types = self::VIDEO_TYPES + self::MUSIC_TYPES;

		return isset($types[$ext]) ? $types[$ext] : null;
	}

	public function delete($name, $type)
	{
		$path = $this->resolve($name, $type);
		if($path === null)
			return false;

		return unlink($path);
	}

	// Invia un file al browser. $inline = true per riprodurlo, false per scaricarlo.
	// Supporta le richieste "Range", così nel player si può spostare la posizione.
	public static function send_file($path, $mime, $inline)
	{
		$size  = filesize($path);
		$start = 0;
		$end   = $size - 1;

		if(isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m))
		{
			if($m[1] === '' && $m[2] !== '')
			{
				$start = max(0, $size - (int)$m[2]); // ultimi N byte
			}
			else
			{
				$start = (int)$m[1];
				if($m[2] !== '')
					$end = min((int)$m[2], $size - 1);
			}

			if($start > $end || $start >= $size)
			{
				header("HTTP/1.1 416 Range Not Satisfiable");
				header("Content-Range: bytes */".$size);
				exit;
			}

			header("HTTP/1.1 206 Partial Content");
			header("Content-Range: bytes ".$start."-".$end."/".$size);
		}

		$filename = basename($path);
		$disposition = $inline ? 'inline' : 'attachment';

		header("Content-Type: ".$mime);
		header("Content-Length: ".($end - $start + 1));
		header("Accept-Ranges: bytes");
		header("Content-Disposition: ".$disposition."; filename=\"".str_replace('"', '', $filename)."\"; filename*=UTF-8''".rawurlencode($filename));
		header("Cache-Control: private, no-cache");

		set_time_limit(0);
		while(ob_get_level() > 0)
			ob_end_clean();

		$fp = fopen($path, 'rb');
		fseek($fp, $start);
		$remaining = $end - $start + 1;

		while($remaining > 0 && !feof($fp) && connection_status() == 0)
		{
			$chunk = fread($fp, min(8192 * 8, $remaining));
			echo $chunk;
			flush();
			$remaining -= strlen($chunk);
		}

		fclose($fp);
	}

	private function outuput_folder_exists()
	{
		if(!is_dir($this->get_downloads_folder()))
		{
			//Folder doesn't exist
			if(!mkdir($this->get_downloads_folder(), 0775))
			{
				return false; //No folder and creation failed
			}
		}

		return true;
	}

	public function to_human_filesize($bytes, $decimals = 0)
	{
		$sz = 'BKMGTP';
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
	}

	public function free_space()
	{
		return $this->to_human_filesize(disk_free_space($this->get_downloads_folder()));
	}

	public function get_downloads_folder()
	{
		$path = $this->config["outputFolder"];
		if(strpos($path, "/") !== 0)
		{
			$path = dirname(__DIR__).'/'.$path;
		}
		return $path;
	}
}

?>
