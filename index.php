<?php
	require_once 'class/Session.php';
	require_once 'class/Downloader.php';
	require_once 'class/FileHandler.php';

	$session = Session::getInstance();

	if(!$session->is_logged_in())
	{
		header("Location: login.php");
		exit;
	}

	if(isset($_GET['kill']) && $_GET['kill'] === "all")
	{
		Downloader::kill_them_all();
		header("Location: index.php");
		exit;
	}

	if(isset($_POST['urls']) && trim($_POST['urls']) !== '')
	{
		$options = Downloader::normalize_options($_POST);
		$_SESSION['dl_options'] = $options; // ricorda le ultime scelte

		$downloader = new Downloader($_POST['urls'], $options);

		if(!isset($_SESSION['errors']))
		{
			header("Location: index.php");
			exit;
		}
	}

	$file = new FileHandler;
	$opt = isset($_SESSION['dl_options']) ? $_SESSION['dl_options'] : Downloader::default_options();

	function sel($current, $value) { return ((string)$current === (string)$value) ? ' selected' : ''; }
	function chk($flag) { return $flag ? ' checked' : ''; }

	require 'views/header.php';
?>
		<div class="container">
			<h1 class="mb-3">Download</h1>
			<?php
				if(isset($_SESSION['errors']) && count($_SESSION['errors']) > 0)
				{
					foreach ($_SESSION['errors'] as $e)
					{
						echo "<div class=\"alert alert-warning\" role=\"alert\">$e</div>";
					}
				}
			?>
			<form id="download-form" action="index.php" method="post">
				<div class="mb-3">
					<input class="form-control form-control-lg" id="url" name="urls" placeholder="Link(s) separated by a comma" type="text">
				</div>

				<div class="d-flex align-items-center gap-3 mb-3">
					<button type="submit" class="btn btn-primary"><i class="bi bi-download me-1"></i> Download</button>
					<a class="link-primary text-decoration-none" data-bs-toggle="collapse" href="#options" role="button" aria-expanded="false" aria-controls="options">
						<i id="options-icon" class="bi bi-sliders d-inline-block me-1" style="transition: transform .3s ease-in-out;"></i> Options
					</a>
				</div>

				<div class="collapse" id="options">
					<div class="card card-body mb-3">
						<div class="row g-3">

							<!-- Tipo di download -->
							<div class="col-md-6 col-lg-4">
								<label class="form-label" for="mode">Download</label>
								<select class="form-select" id="mode" name="mode">
									<option value="video"<?php echo sel($opt['audio'] ? 'audio' : 'video', 'video'); ?>>Video</option>
									<option value="audio"<?php echo sel($opt['audio'] ? 'audio' : 'video', 'audio'); ?>>Audio only</option>
								</select>
							</div>
							<div class="w-100 d-none d-lg-block m-0"></div>

							<!-- Opzioni video -->
							<div class="col-md-6 col-lg-4 video-opt">
								<label class="form-label" for="quality">Max quality</label>
								<select class="form-select" id="quality" name="quality">
									<option value="best"<?php echo sel($opt['quality'], 'best'); ?>>Best available</option>
									<option value="2160"<?php echo sel($opt['quality'], '2160'); ?>>2160p (4K)</option>
									<option value="1440"<?php echo sel($opt['quality'], '1440'); ?>>1440p</option>
									<option value="1080"<?php echo sel($opt['quality'], '1080'); ?>>1080p</option>
									<option value="720"<?php echo sel($opt['quality'], '720'); ?>>720p</option>
									<option value="480"<?php echo sel($opt['quality'], '480'); ?>>480p</option>
									<option value="360"<?php echo sel($opt['quality'], '360'); ?>>360p</option>
								</select>
							</div>
							<div class="col-md-6 col-lg-4 video-opt">
								<label class="form-label" for="container">Container</label>
								<select class="form-select" id="container" name="container">
									<option value="auto"<?php echo sel($opt['container'], 'auto'); ?>>Automatic</option>
									<option value="mp4"<?php echo sel($opt['container'], 'mp4'); ?>>MP4 (most compatible)</option>
									<option value="mkv"<?php echo sel($opt['container'], 'mkv'); ?>>MKV</option>
								</select>
							</div>
							<div class="col-md-6 col-lg-4 video-opt">
								<label class="form-label" for="subs">Subtitles</label>
								<select class="form-select" id="subs" name="subs">
									<option value="none"<?php echo sel($opt['subs'], 'none'); ?>>None</option>
									<option value="write"<?php echo sel($opt['subs'], 'write'); ?>>Separate files</option>
									<option value="embed"<?php echo sel($opt['subs'], 'embed'); ?>>Embedded in video</option>
								</select>
							</div>
							<div class="col-md-6 col-lg-4 video-opt">
								<label class="form-label" for="sub_langs">Subtitle languages</label>
								<input class="form-control" id="sub_langs" name="sub_langs" type="text" value="<?php echo htmlspecialchars($opt['sub_langs']); ?>" placeholder="it,en">
								<div class="form-check mt-2">
									<input class="form-check-input" type="checkbox" id="auto_subs" name="auto_subs" value="1"<?php echo chk($opt['auto_subs']); ?>>
									<label class="form-check-label" for="auto_subs">Include auto-generated</label>
								</div>
							</div>

							<!-- Opzioni audio -->
							<div class="col-md-6 col-lg-4 audio-opt">
								<label class="form-label" for="audio_format">Audio format</label>
								<select class="form-select" id="audio_format" name="audio_format">
									<option value="mp3"<?php echo sel($opt['audio_format'], 'mp3'); ?>>MP3</option>
									<option value="m4a"<?php echo sel($opt['audio_format'], 'm4a'); ?>>M4A (AAC)</option>
									<option value="opus"<?php echo sel($opt['audio_format'], 'opus'); ?>>Opus</option>
									<option value="flac"<?php echo sel($opt['audio_format'], 'flac'); ?>>FLAC (lossless)</option>
									<option value="best"<?php echo sel($opt['audio_format'], 'best'); ?>>Original (no conversion)</option>
								</select>
							</div>
							<div class="col-md-6 col-lg-4 audio-opt">
								<label class="form-label" for="audio_quality">Audio quality</label>
								<select class="form-select" id="audio_quality" name="audio_quality">
									<option value="0"<?php echo sel($opt['audio_quality'], '0'); ?>>Best (VBR)</option>
									<option value="320K"<?php echo sel($opt['audio_quality'], '320K'); ?>>320 kbps</option>
									<option value="256K"<?php echo sel($opt['audio_quality'], '256K'); ?>>256 kbps</option>
									<option value="192K"<?php echo sel($opt['audio_quality'], '192K'); ?>>192 kbps</option>
									<option value="128K"<?php echo sel($opt['audio_quality'], '128K'); ?>>128 kbps</option>
								</select>
							</div>

							<!-- Opzioni comuni -->
							<div class="col-md-6 col-lg-4">
								<label class="form-label" for="template">File name</label>
								<select class="form-select" id="template" name="template">
									<option value="title"<?php echo sel($opt['template'], 'title'); ?>>Title</option>
									<option value="title_id"<?php echo sel($opt['template'], 'title_id'); ?>>Title [ID]</option>
									<option value="uploader_title"<?php echo sel($opt['template'], 'uploader_title'); ?>>Channel - Title</option>
									<option value="playlist"<?php echo sel($opt['template'], 'playlist'); ?>>Playlist folder / 001 - Title</option>
								</select>
							</div>
							<div class="col-md-6 col-lg-4">
								<label class="form-label" for="sponsorblock">SponsorBlock</label>
								<select class="form-select" id="sponsorblock" name="sponsorblock">
									<option value="none"<?php echo sel($opt['sponsorblock'], 'none'); ?>>Off</option>
									<option value="mark"<?php echo sel($opt['sponsorblock'], 'mark'); ?>>Mark as chapters</option>
									<option value="remove"<?php echo sel($opt['sponsorblock'], 'remove'); ?>>Remove sponsors</option>
								</select>
							</div>
							<div class="col-md-6 col-lg-4">
								<label class="form-label" for="rate_limit">Speed limit</label>
								<select class="form-select" id="rate_limit" name="rate_limit">
									<option value=""<?php echo sel($opt['rate_limit'], ''); ?>>Unlimited</option>
									<option value="500K"<?php echo sel($opt['rate_limit'], '500K'); ?>>500 KB/s</option>
									<option value="1M"<?php echo sel($opt['rate_limit'], '1M'); ?>>1 MB/s</option>
									<option value="2M"<?php echo sel($opt['rate_limit'], '2M'); ?>>2 MB/s</option>
									<option value="5M"<?php echo sel($opt['rate_limit'], '5M'); ?>>5 MB/s</option>
									<option value="10M"<?php echo sel($opt['rate_limit'], '10M'); ?>>10 MB/s</option>
								</select>
							</div>

							<div class="col-12">
								<div class="form-check form-check-inline">
									<input class="form-check-input" type="checkbox" id="playlist" name="playlist" value="1"<?php echo chk($opt['playlist']); ?>>
									<label class="form-check-label" for="playlist">Download whole playlist</label>
								</div>
								<div class="form-check form-check-inline">
									<input class="form-check-input" type="checkbox" id="embed_metadata" name="embed_metadata" value="1"<?php echo chk($opt['embed_metadata']); ?>>
									<label class="form-check-label" for="embed_metadata">Embed metadata</label>
								</div>
								<div class="form-check form-check-inline">
									<input class="form-check-input" type="checkbox" id="embed_thumbnail" name="embed_thumbnail" value="1"<?php echo chk($opt['embed_thumbnail']); ?>>
									<label class="form-check-label" for="embed_thumbnail">Embed thumbnail</label>
								</div>
								<div class="form-check form-check-inline video-opt">
									<input class="form-check-input" type="checkbox" id="embed_chapters" name="embed_chapters" value="1"<?php echo chk($opt['embed_chapters']); ?>>
									<label class="form-check-label" for="embed_chapters">Embed chapters</label>
								</div>
								<div class="form-check form-check-inline">
									<input class="form-check-input" type="checkbox" id="restrict" name="restrict" value="1"<?php echo chk($opt['restrict']); ?>>
									<label class="form-check-label" for="restrict">Safe file names (ASCII only)</label>
								</div>
							</div>

							<div class="col-12">
								<label class="form-label" for="extra">Extra options</label>
								<input class="form-control font-monospace" id="extra" name="extra" type="text" value="<?php echo htmlspecialchars($opt['extra']); ?>" placeholder="e.g. --cookies-from-browser firefox --limit-rate 3M">
								<div class="form-text">Any other yt-dlp option, added after the ones above (so it can override them). Options that run commands or change paths (--exec, -o, -P, --config-location, ...) are not allowed.</div>
							</div>

						</div>
					</div>
				</div>
			</form>

			<div class="row g-4 mt-1">
				<div class="col-lg-6">
					<div class="card h-100">
						<div class="card-header text-bg-info"><i class="bi bi-info-circle me-2"></i>Info</div>
						<div class="card-body">
							<p>Free space : <?php echo $file->free_space(); ?></p>
							<p class="mb-0">Download folder : <code><?php echo htmlspecialchars($file->get_downloads_folder()); ?></code></p>
						</div>
					</div>
				</div>
				<div class="col-lg-6">
					<div class="card h-100">
						<div class="card-header text-bg-info"><i class="bi bi-question-circle me-2"></i>Help</div>
						<div class="card-body">
							<p class="fw-bold mb-1">How does it work ?</p>
							<p>Simply paste your video link in the field and click "Download"</p>
							<p class="fw-bold mb-1">With which sites does it work?</p>
							<p><a href="https://github.com/yt-dlp/yt-dlp/blob/master/supportedsites.md" target="_blank">Here's</a> a list of the supported sites</p>
							<p class="fw-bold mb-1">Which extra options can I use?</p>
							<p>See the <a href="https://github.com/yt-dlp/yt-dlp#usage-and-options" target="_blank">yt-dlp options list</a>.</p>
							<p class="fw-bold mb-1">How can I download the video on my computer?</p>
							<p class="mb-0">Go to <a href="./list.php?type=v">List of videos</a> and click the <i class="bi bi-download"></i> button next to the file.</p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<script>
		(function() {
			// Mostra le opzioni video o audio in base al tipo di download
			var mode = document.getElementById('mode');
			function update() {
				var isAudio = mode.value === 'audio';
				document.querySelectorAll('.video-opt').forEach(function(el) { el.classList.toggle('d-none', isAudio); });
				document.querySelectorAll('.audio-opt').forEach(function(el) { el.classList.toggle('d-none', !isAudio); });
			}
			mode.addEventListener('change', update);
			update();

			// I cursori diventano verticali all'apertura del pannello e tornano orizzontali alla chiusura
			var panel = document.getElementById('options');
			var icon  = document.getElementById('options-icon');
			panel.addEventListener('show.bs.collapse', function() { icon.style.transform = 'rotate(90deg)'; });
			panel.addEventListener('hide.bs.collapse', function() { icon.style.transform = ''; });
		})();
		</script>
<?php
	unset($_SESSION['errors']);
	require 'views/footer.php';
?>
