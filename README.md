# AQI Bat

This project is a little hack that allows a Linux box to play an audio file whenever the Air Quality Index rises above or falls below specified thresholds. We mostly use this at night, to let us know when to close the window if the AQI is low enough to keep it open when we go to sleep, or, conversely, to let us know when we can open the window if we had to close up the house to keep the smoke out.

Presently this is just a lightweight proof-of-concept / hack. It uses the [Purple Air API](https://www.purpleair.com/sensorlist) to determine the local Air Quality Index.

## Installation and Setup

There are three steps to setting up this project:

1. Install the cron job
2. Set up the web server
3. Configure PulseAudio to run in system mode

Note that since this project is just a hack, it has a couple of hardcoded paths that will need to be updated.

### Cron

The cron script is located at the root of the project. It can run as a non-priviledged user. Set up a cron entry for it that looks something like this:
```
*/10 * * * * /usr/bin/env XDG_RUNTIME_DIR="/run/user/1000" php /home/ga/aqi-bat/aqi-bat.php
```
Alter the path to the script to align with the location where you cloned this project.

Some notes:

1. The example schedule runs the script once every ten minutes. Adjust to suit, but do note that the purple air API has a rate limit that will throttle you if you send too many requests.
2. The sensor URL is written in the prefs.json file in the configuration directory. The configuration directory will be created at $HOME/.aqi-bat on the first run of the tool.
3. To find the URL of a different sensor to use, first focus on an area of the Purple Air map that has the sensor that you are interested in. Click on the download button shown in the screenshot below.

![Purple Air Download Button](docs/img/purple-air-download-btn.png)

4. That button will bring you to the download tool, shown below. Right click on the "Show on Map" link and copy it to the clipboard.

![Purple Air Download Tool](docs/img/purple-air-download-tool.png)

5. Paste the URL into the `sensor-url` entry in the prefs.json file. An example URL is shown below.
```
https://www.purpleair.com/map?lat=37.6834&lng=-122.406137&zoom=14&show=53461
```

### Web Server

Make a symbolic link from your web server directory to the `htdocs` directory in this project.
```
ln -s '/path/to/aqi-bat/htdocs' '/var/www/aqi.bat/htdocs'
```
This project is just a hack, so you'll have to hand-modify the index.php and threshold.php files to point at the configuration directory created by the cron script.

Configure your web server to serve files from this directory. See the [example Apache configuration](examples/apache2/aqi.bat.conf) for a start.

Make sure that the web server can write to the configuration files:
```
sudo chgrp -R www-data $HOME/.aqi-bat
```

### PulseAudio

By default, pulse audio runs in user mode. This is the right configuration for most use cases; however, in user mode it is not possible to play sounds from any environment other than a logged-in user session. In order to play sounds from a web server, you will need to [configure pulse audio to run in system mode](https://www.freedesktop.org/wiki/Software/PulseAudio/Documentation/User/SystemWide/). Please be sure that you [understand the limitations and problems](https://www.freedesktop.org/wiki/Software/PulseAudio/Documentation/User/WhatIsWrongWithSystemWide/) with running in this configuration before you procede.

#### Disable restart of pulseaudio in client / user mode

In /etc/pulse/client.conf:
```
autospawn = no
```

#### Turn off user-mode pulseaudio in systemd
```
sudo systemctl --global disable pulseaudio.service pulseaudio.socket
```

#### Create a pulseadio systemd service for system mode

Create /etc/systemd/system/pulseaudio.service:
```
[Unit]
Description=PulseAudio system server

[Service]
Type=notify
ExecStart=/usr/bin/pulseaudio --daemonize=no --system --realtime --log-target=journal

[Install]
WantedBy=multi-user.target
```

#### Allow anonymous users to use sound

In vi /etc/pulse/system.pa:
```
load-module module-native-protocol-unix auth-anonymous=1
```

#### Enable the pulseaudio systemd service 
``` 
systemctl --system enable pulseaudio.service
systemctl --system start pulseaudio.service
```

#### Play a test sound
```
/usr/bin/env XDG_RUNTIME_DIR="/run/user/1000" mpg123 examples/sounds/test.mp3
```

## About the Name

AQI-Bat was written to run on a small black network computer purchased to run our home network. This computer and was originally mounted upside down underneath a high shelf, and given its size, color, position and purpose was given the name "bat-net". Bat-net provides DHCP and DNS services, and names devices on the local subnet `.bat` rather than `.local`. The AQI server was therefore assigned the name `aqi.bat`, and the project that runs it was named similarly.
