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

	$file  = new FileHandler;
	$t     = isset($_GET['type']) ? $_GET['type'] : '';
	$type  = null;
	$files = [];

	if($t === 'v')
	{
		$type  = "videos";
		$files = $file->listVideos();
	}
	elseif($t === 'm')
	{
		$type  = "songs";
		$files = $file->listMusics();
	}

	if($type !== null && isset($_GET["delete"]))
	{
		$file->delete($_GET["delete"], $t);
		header("Location: list.php?type=".$t);
		exit;
	}

	require 'views/header.php';
?>
		<div class="container">
		<?php
			if($type === null)
			{
				echo "<div class=\"alert alert-warning\" role=\"alert\">No such type !</div>";
			}
			elseif(empty($files))
			{
				echo "<h2 class=\"mb-3\">List of ".$type."</h2>";
				echo "<div class=\"alert alert-warning\" role=\"alert\">No ".$type." !</div>";
			}
			else
			{
		?>
			<h2 class="mb-3">List of <?php echo $type; ?> <span class="badge text-bg-secondary align-middle fs-6"><?php echo count($files); ?></span></h2>
			<div class="table-responsive">
				<table class="table table-striped table-hover align-middle">
					<thead>
						<tr>
							<th class="w-100">Title</th>
							<th class="text-end text-nowrap">Size</th>
							<th class="text-end text-nowrap">Actions</th>
						</tr>
					</thead>
					<tbody>
			<?php
				foreach($files as $f)
				{
					$label = htmlspecialchars($f["name"]);
					$q     = rawurlencode($f["name"]);
					$js    = htmlspecialchars(json_encode($f["name"]), ENT_QUOTES);

					echo "<tr>";
					echo "<td class=\"text-break\">".$label."</td>";
					echo "<td class=\"text-end text-nowrap\">".$f["size"]."</td>";
					echo "<td class=\"text-end text-nowrap\">";
					echo "<a href=\"play.php?f=".$q."\" target=\"_blank\" class=\"btn btn-outline-primary btn-sm\" title=\"Play\"><i class=\"bi bi-play-fill\"></i></a> ";
					echo "<a href=\"download.php?f=".$q."\" class=\"btn btn-primary btn-sm\" title=\"Download\"><i class=\"bi bi-download\"></i></a> ";
					echo "<a href=\"list.php?type=".$t."&amp;delete=".$q."\" class=\"btn btn-danger btn-sm\" title=\"Delete\" onclick=\"return confirm('Delete ' + ".$js." + ' ?');\"><i class=\"bi bi-trash\"></i></a>";
					echo "</td>";
					echo "</tr>";
				}
			?>
					</tbody>
				</table>
			</div>
		<?php
			}
		?>
		</div><!-- End container -->
<?php
	require 'views/footer.php';
?>
