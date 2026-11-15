<?php

// Copy this file to config.php and edit it.
//
// password: md5 hash of the login password, e.g.
//   echo -n 'YOUR_PASSWORD' | md5sum | cut -d ' ' -f 1
// outputFolder: download folder, writable by the web server user, no "/" at the end
// ytdlp: full path of the yt-dlp executable
// max_dl: maximum simultaneous downloads (0 = no limit)

return array(
	"security" => true,
	"password" => "PUT_YOUR_MD5_HASH_HERE",
	"outputFolder" => "/home/YOUR_USER/downloads/yt-dlp",
	"ytdlp" => "/home/YOUR_USER/.local/bin/yt-dlp",
	"extracter" => "ffmpeg",
	"max_dl" => 3);

?>
