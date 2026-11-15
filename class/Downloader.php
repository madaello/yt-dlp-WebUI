<?php
include_once('FileHandler.php');

class Downloader
{
	// ------------------------------------------------------------------
	// Valori ammessi per le opzioni del form (whitelist lato server)
	// ------------------------------------------------------------------
	const QUALITIES       = ['best', '2160', '1440', '1080', '720', '480', '360'];
	const CONTAINERS      = ['auto', 'mp4', 'mkv'];
	const AUDIO_FORMATS   = ['mp3', 'm4a', 'opus', 'flac', 'best'];
	const AUDIO_QUALITIES = ['0', '320K', '256K', '192K', '128K'];
	const SUB_MODES       = ['none', 'write', 'embed'];
	const SPONSOR_MODES   = ['none', 'mark', 'remove'];
	const RATE_LIMITS     = ['', '500K', '1M', '2M', '5M', '10M'];
	const TEMPLATES       = [
		'title'          => '%(title)s.%(ext)s',
		'title_id'       => '%(title)s [%(id)s].%(ext)s',
		'uploader_title' => '%(uploader)s - %(title)s.%(ext)s',
		'playlist'       => '%(playlist_title|NA)s/%(playlist_index|0)03d - %(title)s.%(ext)s',
	];

	// ------------------------------------------------------------------
	// Opzioni vietate nel campo "Extra options": possono eseguire comandi,
	// leggere/scrivere file fuori dalla cartella download o cambiare
	// il percorso di output.
	// ------------------------------------------------------------------
	const BLOCKED_LONG = [
		'--exec', '--exec-before-download', '--netrc-cmd',
		'--output', '--paths', '--output-na-placeholder',
		'--config-location', '--config-locations', '--alias',
		'--batch-file', '--load-info-json', '--print-to-file',
		'--cookies', '--download-archive', '--cache-dir', '--write-pages',
		'--downloader', '--external-downloader',
		'--downloader-args', '--external-downloader-args',
		'--postprocessor-args', '--ppa', '--use-postprocessor',
		'--plugin-dirs', '--ffmpeg-location',
		'--update', '--update-to',
	];
	const BLOCKED_SHORT = ['o', 'P', 'a', 'U'];

	private $urls = [];
	private $config = [];
	private $options = [];
	private $audio_only = false;
	private $errors = [];
	private $download_path = "";

	public function __construct($post, $options = [])
	{
		$this->config = require dirname(__DIR__).'/config/config.php';

		$this->download_path = (new FileHandler())->get_downloads_folder();

		$this->options = self::normalize_options($options);
		$this->audio_only = $this->options['audio'];

		$this->urls = array_values(array_filter(array_map('trim', explode(",", $post)), 'strlen'));

		if(!$this->check_requirements($this->audio_only))
		{
			return;
		}

		foreach ($this->urls as $url)
		{
			if(!$this->is_valid_url($url))
			{
				$this->errors[] = "\"".htmlspecialchars($url)."\" is not a valid url !";
			}
		}

		// Valida subito le opzioni extra, prima di lanciare qualsiasi cosa
		$this->parse_extra($this->options['extra']);

		if(count($this->errors) > 0)
		{
			$_SESSION['errors'] = $this->errors;
			return;
		}

		if($this->config["max_dl"] == 0)
		{
			$this->do_download();
		}
		elseif($this->config["max_dl"] > 0)
		{
			if($this->background_jobs() >= 0 && $this->background_jobs() < $this->config["max_dl"])
			{
				$this->do_download();
			}
			else
			{
				$this->errors[] = "Simultaneous downloads limit reached !";
			}
		}

		if(count($this->errors) > 0)
		{
			$_SESSION['errors'] = $this->errors;
			return;
		}
	}

	// ------------------------------------------------------------------
	// Opzioni: valori di default e normalizzazione dell'input del form
	// ------------------------------------------------------------------
	public static function default_options()
	{
		return [
			'audio'           => false,
			'quality'         => 'best',
			'container'       => 'auto',
			'audio_format'    => 'mp3',
			'audio_quality'   => '192K',
			'subs'            => 'none',
			'sub_langs'       => 'it,en',
			'auto_subs'       => false,
			'embed_metadata'  => true,
			'embed_thumbnail' => false,
			'embed_chapters'  => false,
			'playlist'        => false,
			'sponsorblock'    => 'none',
			'rate_limit'      => '',
			'template'        => 'title',
			'restrict'        => true,
			'extra'           => '',
		];
	}

	public static function normalize_options($in)
	{
		$d = self::default_options();
		if(!is_array($in))
		{
			return $d;
		}

		$o = [];
		$o['audio']           = (isset($in['mode']) && $in['mode'] === 'audio') || !empty($in['audio']);
		$o['quality']         = self::pick($in, 'quality', self::QUALITIES, $d);
		$o['container']       = self::pick($in, 'container', self::CONTAINERS, $d);
		$o['audio_format']    = self::pick($in, 'audio_format', self::AUDIO_FORMATS, $d);
		$o['audio_quality']   = self::pick($in, 'audio_quality', self::AUDIO_QUALITIES, $d);
		$o['subs']            = self::pick($in, 'subs', self::SUB_MODES, $d);
		$o['sponsorblock']    = self::pick($in, 'sponsorblock', self::SPONSOR_MODES, $d);
		$o['rate_limit']      = self::pick($in, 'rate_limit', self::RATE_LIMITS, $d);
		$o['template']        = self::pick($in, 'template', array_keys(self::TEMPLATES), $d);
		$o['auto_subs']       = !empty($in['auto_subs']);
		$o['embed_metadata']  = !empty($in['embed_metadata']);
		$o['embed_thumbnail'] = !empty($in['embed_thumbnail']);
		$o['embed_chapters']  = !empty($in['embed_chapters']);
		$o['playlist']        = !empty($in['playlist']);
		$o['restrict']        = !empty($in['restrict']);

		$langs = isset($in['sub_langs']) ? trim($in['sub_langs']) : '';
		$o['sub_langs'] = preg_match('/^[A-Za-z0-9.*,\-]{1,50}$/', $langs) ? $langs : $d['sub_langs'];

		$o['extra'] = isset($in['extra']) ? trim($in['extra']) : '';

		return $o;
	}

	private static function pick($in, $key, $allowed, $defaults)
	{
		if(isset($in[$key]) && in_array((string)$in[$key], $allowed, true))
		{
			return (string)$in[$key];
		}
		return $defaults[$key];
	}

	// ------------------------------------------------------------------
	// Opzioni extra libere: tokenizzazione + controllo opzioni vietate
	// ------------------------------------------------------------------
	private function parse_extra($str)
	{
		$args = [];
		if($str === '')
		{
			return $args;
		}

		// "testo tra virgolette", 'testo tra apici' oppure parole separate da spazi
		preg_match_all('/"([^"]*)"|\'([^\']*)\'|(\S+)/', $str, $m, PREG_SET_ORDER);

		foreach($m as $t)
		{
			$n = count($t);
			$tok = ($n == 4) ? $t[3] : (($n == 3) ? $t[2] : $t[1]);

			if($this->is_blocked_option($tok))
			{
				$this->errors[] = "Option \"".htmlspecialchars($tok)."\" is not allowed in extra options !";
				continue;
			}
			$args[] = $tok;
		}

		return $args;
	}

	private function is_blocked_option($tok)
	{
		if($tok === '--')
		{
			return true; // il separatore prima degli URL lo mettiamo noi
		}

		// Opzioni lunghe: --nome oppure --nome=valore
		if(strlen($tok) > 2 && substr($tok, 0, 2) === '--')
		{
			$name = strtolower(explode('=', $tok, 2)[0]);
			foreach(self::BLOCKED_LONG as $b)
			{
				if($name === $b)
				{
					return true;
				}
				// abbreviazioni (es. --exe per --exec)
				if(strlen($name) >= 4 && strpos($b, $name) === 0)
				{
					return true;
				}
			}
			return false;
		}

		// Opzioni corte, anche raggruppate (es. -xo)
		if(strlen($tok) > 1 && $tok[0] === '-' && !is_numeric($tok))
		{
			$cluster = substr($tok, 1);
			foreach(self::BLOCKED_SHORT as $s)
			{
				if(strpos($cluster, $s) !== false)
				{
					return true;
				}
			}
		}

		return false;
	}

	// ------------------------------------------------------------------
	// Job in background
	// ------------------------------------------------------------------
	public static function background_jobs()
	{
		return shell_exec("ps aux | grep -v grep | grep -v \"yt-dlp -U\" | grep yt-dlp | wc -l");
	}

	public static function max_background_jobs()
	{
		$config = require dirname(__DIR__).'/config/config.php';
		return $config["max_dl"];
	}

	public static function get_current_background_jobs()
	{
		exec("ps -A -o user,pid,etime,cmd | grep -v grep | grep -v \"yt-dlp -U\" | grep yt-dlp", $output);

		$bjs = [];

		if(count($output) > 0)
		{
			foreach($output as $line)
			{
				$line = explode(' ', preg_replace ("/ +/", " ", trim($line)), 4);
				$bjs[] = array(
					'user' => $line[0],
					'pid' => $line[1],
					'time' => $line[2],
					'cmd' => $line[3]
					);
			}

			return $bjs;
		}
		else
		{
			return null;
		}
	}

	public static function kill_them_all()
	{
		exec("ps -A -o pid,cmd | grep -v grep | grep yt-dlp | awk '{print $1}'", $output);

		if(count($output) <= 0)
			return;

		foreach($output as $p)
		{
			shell_exec("kill ".intval($p));
		}

		// Prima usava $this in un metodo statico (errore fatale): ora legge la cartella dal config
		$folder = (new FileHandler())->get_downloads_folder();

		foreach(glob(rtrim($folder, '/').'/*.part') as $file)
		{
			unlink($file);
		}
	}

	// ------------------------------------------------------------------
	// Controlli preliminari
	// ------------------------------------------------------------------
	private function check_requirements($audio_only)
	{
		if(!$this->is_youtubedl_installed())
		{
			$this->errors[] = "yt-dlp is not installed, see <a href=\"https://github.com/yt-dlp/yt-dlp/wiki/Installation\" target=\"_blank\">https://github.com/yt-dlp/yt-dlp/wiki/Installation</a> !";
		}

		$this->check_outuput_folder();

		if($audio_only)
		{
			if($this->is_extracter_installed() != 0)
			{
				$this->errors[] = "Install an audio extracter (ex: ffmpeg) !";
			}
		}

		if(count($this->errors) > 0)
		{
			$_SESSION['errors'] = $this->errors;
			return false;
		}

		return true;
	}

	private function ytdlp_path()
	{
		return isset($this->config["ytdlp"]) ? $this->config["ytdlp"] : "/usr/local/bin/yt-dlp";
	}

	private function is_youtubedl_installed()
	{
		return is_executable($this->ytdlp_path());
	}

	private function is_extracter_installed()
	{
		exec("which ".escapeshellarg($this->config["extracter"]), $out, $r);
		return $r;
	}

	private function is_valid_url($url)
	{
		return filter_var($url, FILTER_VALIDATE_URL);
	}

	private function check_outuput_folder()
	{
		if(!is_dir($this->download_path))
		{
			//Folder doesn't exist
			if(!mkdir($this->download_path, 0775))
			{
				$this->errors[] = "Output folder doesn't exist and creation failed !";
			}
		}
		else
		{
			//Exists but can I write ?
			if(!is_writable($this->download_path))
			{
				$this->errors[] = "Output folder isn't writable !";
			}
		}
	}

	// ------------------------------------------------------------------
	// Costruzione ed esecuzione del comando
	// ------------------------------------------------------------------
	private function build_args()
	{
		$o = $this->options;
		$args = [$this->ytdlp_path()];

		// Niente barra di avanzamento nel log: solo messaggi ed errori
		$args[] = '--no-progress';

		// Percorso e nome del file
		$args[] = '-o';
		$args[] = rtrim($this->download_path, '/').'/'.self::TEMPLATES[$o['template']];
		if($o['restrict'])
		{
			$args[] = '--restrict-filenames';
		}

		if($this->audio_only)
		{
			$args[] = '-x';
			$args[] = '--audio-format';
			$args[] = $o['audio_format'];
			if($o['audio_format'] !== 'flac')
			{
				$args[] = '--audio-quality';
				$args[] = $o['audio_quality'];
			}
		}
		else
		{
			// Qualità e contenitore
			$sort = [];
			if($o['quality'] !== 'best')
			{
				$sort[] = 'res:'.$o['quality'];
			}
			if($o['container'] === 'mp4')
			{
				$sort[] = 'ext:mp4:m4a'; // preferisce H.264/AAC, più compatibile
			}
			if(count($sort) > 0)
			{
				$args[] = '-S';
				$args[] = implode(',', $sort);
			}
			if($o['container'] !== 'auto')
			{
				$args[] = '--merge-output-format';
				$args[] = $o['container'];
			}

			// Sottotitoli
			if($o['subs'] !== 'none')
			{
				// Se i sottotitoli non si scaricano (YouTube risponde spesso "HTTP 429"
				// a quelli autogenerati) yt-dlp normalmente abbandona anche il video:
				// con -i l'errore viene solo segnalato e il video si scarica lo stesso.
				$args[] = '-i';
				$args[] = '--sleep-subtitles';
				$args[] = '3';
				$args[] = '--write-subs';
				if($o['auto_subs'])
				{
					$args[] = '--write-auto-subs';
				}
				$args[] = '--sub-langs';
				$args[] = $o['sub_langs'];
				if($o['subs'] === 'embed')
				{
					$args[] = '--embed-subs';
				}
			}

			if($o['embed_chapters'])
			{
				$args[] = '--embed-chapters';
			}
		}

		if($o['embed_metadata'])
		{
			$args[] = '--embed-metadata';
		}
		if($o['embed_thumbnail'])
		{
			$args[] = '--embed-thumbnail';
		}

		$args[] = $o['playlist'] ? '--yes-playlist' : '--no-playlist';

		if($o['sponsorblock'] === 'mark')
		{
			$args[] = '--sponsorblock-mark';
			$args[] = 'all';
		}
		elseif($o['sponsorblock'] === 'remove')
		{
			$args[] = '--sponsorblock-remove';
			$args[] = 'sponsor,selfpromo,interaction';
		}

		if($o['rate_limit'] !== '')
		{
			$args[] = '-r';
			$args[] = $o['rate_limit'];
		}

		// Opzioni extra dopo le nostre, così possono sovrascriverle
		foreach($this->parse_extra($o['extra']) as $a)
		{
			$args[] = $a;
		}

		// "--" garantisce che gli URL non vengano interpretati come opzioni
		$args[] = '--';
		foreach($this->urls as $url)
		{
			$args[] = $url;
		}

		return $args;
	}

	// File di log di yt-dlp: .yt-dlp.log nella cartella dei download
	// (fuori dalla cartella web, quindi non leggibile dal browser).
	// Se non si può scrivere lì, l'output viene scartato come prima.
	private function log_file()
	{
		$dir = rtrim($this->download_path, '/');
		$log = $dir.'/.yt-dlp.log';
		if(is_dir($dir) && is_writable($dir) && (!file_exists($log) || is_writable($log)))
		{
			// Tiene il log entro ~2 MB: oltre, ricomincia da capo
			if(file_exists($log) && filesize($log) > 2 * 1024 * 1024)
			{
				@rename($log, $log.'.old');
			}
			return $log;
		}

		return '/dev/null';
	}

	private function do_download()
	{
		$args = $this->build_args();
		$log  = $this->log_file();

		// Intestazione nel log: data e link, per ritrovare il download giusto
		if($log !== '/dev/null')
		{
			@file_put_contents($log, "\n===== ".date('Y-m-d H:i:s')." ".implode(' ', $this->urls)." =====\n", FILE_APPEND);
		}

		$cmd = implode(' ', array_map('escapeshellarg', $args));
		$cmd .= " >> ".escapeshellarg($log)." 2>&1 & echo $!";

		shell_exec($cmd);
	}
}

?>
