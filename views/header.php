<?php
	// Pagina corrente, per evidenziare la voce attiva nel menu
	$current_page = basename($_SERVER['PHP_SELF']);
	$current_type = isset($_GET['type']) ? $_GET['type'] : '';

	function nav_active($cond) { return $cond ? ' active" aria-current="page' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>yt-dlp WebUI</title>
		<link rel="icon" type="image/x-icon" href="favicon.ico">
		<script>
			// Tema chiaro/scuro in base all'impostazione del sistema operativo
			(function() {
				var mq = window.matchMedia('(prefers-color-scheme: dark)');
				function apply() { document.documentElement.setAttribute('data-bs-theme', mq.matches ? 'dark' : 'light'); }
				apply();
				mq.addEventListener('change', apply);
			})();
		</script>
		<link rel="stylesheet" href="css/bootstrap.min.css">
		<link rel="stylesheet" href="css/bootstrap-icons.min.css">
	</head>
	<body class="d-flex flex-column min-vh-100">
		<nav class="navbar navbar-expand-lg bg-body-tertiary mb-4">
			<div class="container-fluid">
				<a class="navbar-brand" href="./">yt-dlp WebUI</a>
				<?php
					// Senza login (pagina di login) si mostra solo il nome a sinistra
					if($session->is_logged_in())
					{
				?>
				<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>
				<div class="collapse navbar-collapse" id="mainNav">
					<ul class="navbar-nav me-auto">
						<li class="nav-item"><a class="nav-link<?php echo nav_active($current_page === 'index.php'); ?>" href="./">Download</a></li>
						<li class="nav-item"><a class="nav-link<?php echo nav_active($current_page === 'list.php' && $current_type === 'v'); ?>" href="./list.php?type=v">List of videos</a></li>
						<li class="nav-item"><a class="nav-link<?php echo nav_active($current_page === 'list.php' && $current_type === 'm'); ?>" href="./list.php?type=m">List of songs</a></li>
						<?php
							if($session->is_logged_in())
							{
								$jobs_count = (int)Downloader::background_jobs();
								$jobs_max   = Downloader::max_background_jobs();
								$jobs       = Downloader::get_current_background_jobs();
						?>
						<li class="nav-item dropdown">
							<a class="nav-link dropdown-toggle<?php echo $jobs_count > 0 ? ' fw-bold' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
								Background downloads : <?php echo $jobs_count." / ".$jobs_max; ?>
							</a>
							<ul class="dropdown-menu">
								<?php
									if($jobs != null)
									{
										foreach($jobs as $job)
										{
											$icon = (strpos($job['cmd'], ' -x ') !== false || strpos($job['cmd'], "'-x'") !== false) ? 'bi-music-note-beamed' : 'bi-camera-video';
											echo "<li><span class=\"dropdown-item-text\"><i class=\"bi ".$icon." me-2\"></i>Elapsed time : ".htmlspecialchars($job['time'])."</span></li>";
										}
										echo "<li><hr class=\"dropdown-divider\"></li>";
										echo "<li><a class=\"dropdown-item text-danger\" href=\"./index.php?kill=all\" onclick=\"return confirm('Stop all running downloads?');\"><i class=\"bi bi-x-octagon me-2\"></i>Kill all downloads</a></li>";
									}
									else
									{
										echo "<li><span class=\"dropdown-item-text text-body-secondary\">No jobs !</span></li>";
									}
								?>
							</ul>
						</li>
						<?php
							}
						?>
					</ul>
					<?php
						if($session->is_logged_in())
						{
					?>
					<ul class="navbar-nav">
						<li class="nav-item"><a class="nav-link" href="./logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
					</ul>
					<?php
						}
					?>
				</div>
				<?php
					}
				?>
			</div>
		</nav>
		<main class="flex-grow-1">
