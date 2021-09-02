# nl.pum.amsco

This CiviCRM-extension can import CSV-data for AMSCO Applications.
It first reads the imported CSV-file, then it:
* creates a CiviCRM contact
* creates the contact data for the contact
* creates a authorised contact linked as relationship to the main contact
* It fill the customer data like yearly information and other data


The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v5.6 (Tested, might work with other versions, but not tested)
* CiviCRM v4.4.8 (Tested, might work with other versions, but not tested)
* OneDrive client for Linux

## Installation

Put the extension nl.pum.amsco in the CiviCRM-extensions directory
Goto Administer -> System Settings -> Manage Extensions -> Click Refresh
Now press install on the ‘Amsco Applications’ extension.

## Permissions

Goto People -> Permissions ->
Enable permission 'CiviCRM AMSCO: import amsco application' for the user roles that are allowed to import AMSCO Applications.

## Configuration
#### Installation of OneDrive
This extension requires a onedrive instance on the Linux server.
````
#sudo su -
#apt-get install libcurl4-openssl-dev libsqlite3-dev
#wget http://master.dl.sourceforge.net/project/d-apt/files/d-apt.list -O /etc/apt/sources.list.d/d-apt.list
#apt-get update && sudo apt-get -y --allow-unauthenticated install --reinstall d-apt-keyring
#apt-get update && sudo apt-get install dmd-compiler dub git
#cd /opt
#git clone https://github.com/abraunegg/onedrive.git
/opt# cd onedrive
/opt/onedrive# ./configure
/opt/onedrive# make
/opt/onedrive# make install

#onedrive --help
#onedrive --display-config
````

#### Configuration of OneDrive
````
/#cd /opt/onedrive
/opt/onedrive# onedrive
````
This gives an URL.
Log on to this URL in a webbrowser using the account that you want to use to synchronise OneDrive with
Then open the given URL and give access using the allow button on the page that is showing up.
Then an empty page is shown. You need to copy the URL of this empty page, and paste is in the response URI that is shown in Ubuntu

The default location of OneDrive = /root/OneDrive
But i've changed this in /var/OneDriveAmsco/:

To simplify the instructions i've used root as the account to synchronise the OneDrive, but I advice to create a separate user for the OneDrive sync client.

````
#mkdir /var/OneDriveAmsco/
#chown -R root:root /var/OneDriveAmsco/
#chmod -R 755 /var/OneDriveAmsco/
#cd /var/OneDriveAmsco/
#find . -type d -exec chmod u=rwx,g=rx,o=rx '{}' \;
#find . -type f -exec chmod u=rw,g=r,o=r '{}' \;
````

Create a config file to specify additional options:
````
#pico /root/.config/onedrive/config

# Configuration for OneDrive Linux Client
# This file contains the list of supported configuration fields
# with their default values.
# All values need to be enclosed in quotes
# When changing a config option below, remove the '#' from the start of the line
# For explanations of all config options below see docs/USAGE.md or the man page.
#
sync_dir = "/var/OneDriveAmsco"
skip_file = "~*|.~*|*.tmp"
monitor_interval = "300"
skip_dir = ""
log_dir = "/var/log/"
# drive_id = ""
upload_only = "false"
# check_nomount = "false"
check_nosync = "false"
# download_only = "false"
# disable_notifications = "false"
# disable_upload_validation = "false"
enable_logging = "true"
# force_http_2 = "false"
# local_first = "false"
no_remote_delete = "false"
skip_symlinks = "false"
# debug_https = "false"
skip_dotfiles = "false"
# dry_run = "false"
min_notify_changes = "5"
# monitor_log_frequency = "5"
# monitor_fullscan_frequency = "10"
sync_root_files = "false"
classify_as_big_delete = "1000"
# user_agent = ""
remove_source_files = "false"
# skip_dir_strict_match = "false"
# application_id = ""
# resync = "false"
# bypass_data_preservation = "false"
# azure_ad_endpoint = ""
# azure_tenant_id = "common"
# sync_business_shared_folders = "false"
sync_dir_permissions = "750"
sync_file_permissions = "660"
# rate_limit = "131072"
````

If everything is configured correctly the OneDrive folder in the /root folder can be removed:
````
#cd /root
#rm -rf OneDrive/
````

#### Create synchronisation script for OneDrive
Create a synchronisation script that automatically synchronises the OneDrive folder:
````
#pico  /usr/local/sbin/synchronize_onedrive_amsco.sh

cd /
cd /var/OneDriveAmsco
onedrive --synchronize --verbose --enable-logging --log-dir /var/log/
````

Set permissions:
````
#chown root:root synchronize_onedrive_amsco.sh
#chmod 744 synchronize_onedrive_amsco.sh
````

Create a scheduled job:
````
#pico /etc/crontab
0 1  * * * root  /usr/local/sbin/synchronize_onedrive_amsco.sh
30 2	* * *	root	/usr/bin/drush -r /var/www/drupal civicrm-api Amsco.Download
````


## Usage
When a csv-file is uploaded to the OneDrive the extension will automatically process this .csv-file using the scheduled job.
Eventually you can also import a .csv-file manually via: Cases -> Import Amso Application, which brings up a screen that can be used to import the .csv-file


## Known Issues


