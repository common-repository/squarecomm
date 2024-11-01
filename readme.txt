=== Plugin Name ===

Contributors: sssowner 
Donate link: http://squarestatesoftware.com/donate
Tags: Wordpress REST API, WooCommerce REST API, REST API, Wordpress API, eCommerce, IOS, mobile, app, REST, API, MySQL, Linux, WooCommerce, Wordpress, Plugin, Multisite
Requires at least: 4.0
License: GPLv2 or later
Stable tag: 2.2.0
Tested up to: 4.7 
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SquareComm REST API. This plugin serves the required REST API to the SquareComm mobile app.

== Description ==

This plugin provides an easy to use REST API that is required for the SquareComm mobile app. This plugin accepts simple REST API commands and returns JSON formatted responses, including products, product variations, media, skus, and more. Retrieving and updating data is as simple as sending a HTTP request.

**Primary REST endpoints:**

/SquareComm/products

/SquareComm/products/id

/SquareComm/products/id/variations

/SquareComm/products/id/variation/id

/SquareComm/media

/SquareComm/skus



= Support =

SquareComm is under active development and I will do my best to provide fixes to problems. The latest general releases are always available through the WordPress Plugins Directory.

For general questions, please post on the WordPress forums with the tag SquareComm. For bug reports or if you wish to suggest patches or fixes, please go to: <a href="https://www.squarestatesoftware.com/squarecomm-plugin/">SquareComm Plugin</a>.

If you have any problems with SquareComm, it would be helpful if you could enable PHP error loging by editing your php.ini file. 

**Example php.ini:**

; Log errors to specified file. PHP's default behavior is to leave this value empty.
error_log = /var/log/php_error.log
 

Include the contents of your php error log file in your correspondence with the developer. Thanks.
 
**Disclaimer** Although SquareComm has been well tested and is used on production web sites, it changes database content which holds the possibility of breaking things. Use of SquareComm is at your own risk! Please make sure you have adequate backups and if you do find any problems please report them.

== Installation ==

Install the SquareComm Plugin/API via the plugin directory, or by uploading the files manually to your server.

Once you've installed and activated the plugin, [check out the documentation](http://squarestatesoftware.com/squarecomm-plugin) for details on your newly available REST API endpoints.

= Requirements =

SquareComm uses shell commands to do some of its stuff, so there is more of a chance of things going wrong than with most plugins. Please check that your set up meets these requirements.

SquareComm is currently well tested on:

* Linux

platforms.

== Frequently Asked Questions ==

= TBD =

== Screenshots ==

= TBD =

== Changelog ==

= 2.0.1 (April 2, 2016) =

= 2.1.1 (April 15, 2016) =

Decouple the database backup process from the request dispatch and handling.

= 2.1.2 (April 15, 2016) =

Bug fixes:

1. Handle handshake request properly (don't require database information from client).

= 2.1.3 (September 25, 2016) =

Support Wordpress multisites for SquareComm app sample databases.

= 2.1.4 (December 9, 2016) =

Support file uploads for SquareComm app sample databases.

= 2.1.5 (March 22, 2017) =

Bug fixes.

= 2.1.6 (May 10, 2017) =

Change some JSON key/value text to more appropriate verbiage.

= 2.2.0 (May 13, 2017) =

Solidify Media add/delete in API

== Upgrade Notice ==

= TBD =


