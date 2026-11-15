# yt-dlp WebUI

A small self-hosted web interface for [yt-dlp](https://github.com/yt-dlp/yt-dlp): paste one or more links, choose video or audio, and the download runs in the background on your server. When it's done you can play the file in the browser or save it.

Based on [Youtube-dl WebUI](https://github.com/p1rox/Youtube-dl-WebUI) by p1rox, rewritten to work with yt-dlp.

## Features

- **Video:** maximum resolution (from 360p to 2160p), MP4 or MKV container.
- **Audio only:** MP3, M4A, Opus or FLAC, with selectable quality.
- Subtitles (saved or embedded, optionally auto-generated), metadata, thumbnail and chapters.
- Playlists, SponsorBlock (mark or remove segments), download speed limit, file name templates.
- Several simultaneous downloads in the background, with a configurable limit and a "kill all" button.
- Lists of videos and songs, including playlist subfolders: play in the browser (with seeking) or download.
- Bootstrap 5 with light and dark theme following the system setting; all libraries are included, nothing is loaded from the internet.
- Password-protected.

### Security

The "Extra options" field passes options straight to yt-dlp, so options that could run commands or write outside the download folder (`--exec`, `-o`, `--paths`, `--config-location`, `--batch-file`, …) are rejected. All other choices are checked against a list of allowed values, and every argument is escaped before running yt-dlp.

The yt-dlp log and the play/download logs are written as hidden files in the download folder, outside the web folder.

## Requirements

- A web server with PHP (tested with PHP 8 on Raspberry Pi OS / Debian 12)
- [yt-dlp](https://github.com/yt-dlp/yt-dlp/wiki/Installation)
- ffmpeg, for merging video and audio and for audio extraction

## Installation

1. Install ffmpeg and yt-dlp:

   ```bash
   sudo apt install ffmpeg
   mkdir -p ~/.local/bin
   wget https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -O ~/.local/bin/yt-dlp
   chmod a+rx ~/.local/bin/yt-dlp
   ```

2. Clone this repository into your web folder, for example:

   ```bash
   cd /var/www/html
   sudo git clone https://github.com/madaello/yt-dlp-WebUI.git yt-dlp
   ```

3. Create the download folder, writable by the web server user (`www-data`):

   ```bash
   mkdir -p ~/downloads/yt-dlp
   sudo chgrp www-data ~/downloads/yt-dlp
   chmod 775 ~/downloads/yt-dlp
   ```

   The web server user must also be able to reach it: your home folder needs at least execute permission for others (`chmod o+x ~`).

4. Create the configuration:

   ```bash
   cd /var/www/html/yt-dlp/config
   sudo cp config.sample.php config.php
   sudo nano config.php
   ```

   Set `outputFolder` and `ytdlp` to your paths, and `password` to the md5 hash of your password:

   ```bash
   echo -n 'YOUR_PASSWORD' | md5sum | cut -d ' ' -f 1
   ```

5. Open `http://YOUR_SERVER/yt-dlp/` in the browser.

A step-by-step guide (in Italian) to installing it on a Raspberry Pi with Apache: [yt-dlp sul Raspberry Pi](https://www.nwp.to.it/installazione-yt-dlp/).

### Keep yt-dlp up to date

Sites change often and an old yt-dlp soon stops working. Add a weekly update to your crontab (`crontab -e`):

```
0 5 * * 1 $HOME/.local/bin/yt-dlp -U
```

### Updating the interface

```bash
cd /var/www/html/yt-dlp
sudo git pull
```

`config/config.php` is not part of the repository, so your settings are kept.

## Credits

- [p1rox](https://github.com/p1rox) for the original [Youtube-dl WebUI](https://github.com/p1rox/Youtube-dl-WebUI)
- [yt-dlp](https://github.com/yt-dlp/yt-dlp)
- [Bootstrap](https://getbootstrap.com/) and [Bootstrap Icons](https://icons.getbootstrap.com/)

## License

MIT, like the original project. See [LICENSE](LICENSE).
