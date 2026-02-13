=== WPManage by Timepro ===
Contributors: Timepro
Tags: management, monitoring, dashboard, updates, backup
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 0.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress management plugin connecting to the Timepro WP Dashboard for centralized site monitoring.

== Description ==

WPManage by Timepro is a powerful WordPress management plugin that connects your WordPress site to the Timepro WP Dashboard. It automatically collects and sends site information for centralized monitoring and management.

= Key Features =

* **Automatic Updates** - Plugin updates delivered automatically from Timepro server
* **Site Profiling** - Collects WordPress, PHP, MySQL versions, theme, and admin details
* **Backup Monitoring** - Tracks UpdraftPlus backup status and last backup time
* **Scheduled Sync** - Automatically sends data every 12 hours
* **Manual Pull** - Supports on-demand data retrieval
* **Lightweight** - Minimal impact on site performance

= Data Collected =

* Site URL
* WordPress version
* PHP version
* MySQL version
* Active theme name
* Admin username and email
* Site email address
* Plugin version
* Last backup time (if UpdraftPlus is installed)

= Privacy =

This plugin collects and transmits site information to https://beta.timepro.in for monitoring purposes. The data includes technical information about your WordPress installation and admin user details. No content or user data is collected.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wpmanage-by-timepro/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. The plugin will automatically start collecting and sending data

No configuration required - it works out of the box!

== Frequently Asked Questions ==

= How often does the plugin send data? =

The plugin sends data every 12 hours to prevent server overload.

= Can I manually trigger data collection? =

Yes, visit `yoursite.com/?_profile_data=1` to view collected data and force a sync.

= Does this work with UpdraftPlus? =

Yes! If UpdraftPlus is installed, the plugin will automatically track your last backup time.

= How do updates work? =

Updates are delivered automatically from the Timepro server. You'll see update notifications in your WordPress admin just like any other plugin.

= Is my data secure? =

Data is transmitted over HTTPS to the Timepro server. Only technical site information is collected.

== Screenshots ==

1. Plugin automatically collects site information
2. Updates delivered from custom update server
3. Integration with UpdraftPlus backup monitoring

== Changelog ==

= 0.0.2 =
* Initial release
* Automatic data collection and transmission
* WordPress, PHP, and MySQL version tracking
* Active theme detection
* Admin user information collection
* UpdraftPlus backup status integration
* Auto-update functionality via custom update server
* Manual data pull via URL parameter

== Upgrade Notice ==

= 0.0.2 =
Initial release of WPManage by Timepro.

== Developer Notes ==

= Manual Data Access =

* View data: `?_profile_data=1`
* Pull JSON: `?_pull_profile_data=yes`

= Constants =

* `TIMEPRO_URL` - Base URL for Timepro server
* `WPMANAGE_VERSION` - Current plugin version
* `WPMANAGE_UPDATE_URL` - Update check endpoint
* `LAST_SENT_AT` - Transient key for sync tracking

= Filters & Actions =

Currently no custom filters or actions available.

== Support ==

For support, visit https://beta.timepro.in
