<?php
	require_once 'class/Session.php';
	require_once 'class/Downloader.php';

	$session = Session::getInstance();
	$loginError = "";

	if(isset($_POST["password"]))
	{
		if($session->login($_POST["password"]))
		{
			header("Location: index.php");
			exit;
		}
		else
		{
			$loginError = "Wrong password !";
		}
	}

	require 'views/header.php';
?>
		<div class="container">
			<div class="row justify-content-center">
				<div class="col-sm-8 col-md-6 col-lg-4 mt-4">
					<?php
						if($loginError !== "")
						{
							echo "<div class=\"alert alert-danger\" role=\"alert\">".$loginError."</div>";
						}
					?>
					<form action="login.php" method="POST">
						<div class="mb-3">
							<input class="form-control" id="password" name="password" placeholder="Password" type="password" autofocus required>
						</div>
						<button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i> Login</button>
					</form>
				</div>
			</div>
		</div>
<?php require 'views/footer.php'; ?>
