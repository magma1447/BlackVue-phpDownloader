# BlackVue-phpDownloader
Automatically downloads video files from your BlackVue DashCam. It of course needs to be connected to your network, via WiFi for example.

# Schedule
Schedule it by creating /etc/cron.d/BlackVue-phpDownloader and adding the following to it. Replace root with a username which does not have evelated privileges.
```
MAILTO=root
*/5 * * * * root /usr/bin/php -f /usr/local/BlackVue-phpDownloader/BlackVue-phpDownloader.php >/dev/null
```
