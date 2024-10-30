=== LCS Security ===
Plugin URI: http://www.latcomsystems.com/index.cfm?SheetIndex=wp_lcs_security
Contributors: latcomsystems
Tags: security,protection,attack,hacker,virus,ddos,brute,force,xmlrpc,comment,spam,login,password,user,captcha,log,malicious,script,vulnerability
Requires at least: 3.5
Tested up to: 5.7
Stable tag: 2.5
License: GPLv2
License URI: http://www.gnu.org/licenses/agpl-2.0.html

This plugin adds a comprehensive suite of security measures to WordPress.

== Description ==

This plugin adds a comprehensive suite of security measures to WordPress.  Simply install, activate, and rest assured that your site is now protected against most common attacks.

We attempted to create the "Goldilocks" of Wordpress security by finding a happy medium between the really complicated plugins that seem to slow down your site and often break functionality because they are too restrictive, and the piece-meal ones that only address one or two vulnerabilities at a time.

The following areas of security weakness are addressed:

* XML RPC Protection - stops unauthorized content injection.
* Author Scanning Prevention - prevents revealing of user login names.
* Malicious Script Blocking - stops execution of scripts in specific vulnerable directories.
* Comment Spam Prevention - adds a CAPTCHA to the comment form.
* User Login Protection - includes automatic timed failed login attempt lockouts and CAPTCHA for login page.
* Automatic IP Ban - bans IP's from the entire site based on number of failed login attempts.
* IP Blacklist - allows adding known bad IP's and bans them from the entire site.
* IP Whitelist - allows adding known good IP's.

This plugin also provides a log of all login attempts including geographical IP data.

Temporarily locked IP's can be unlocked by the administrator.

Permanently banned IP's can be un-banned by the administrator.

CAUTION:  Do not use this plugin with other security plugins to avoid conflicts and other site issues.  Use only one active security suite at a time.

== Installation ==

1. Download the latest zip file and extract the `lcs-security` directory.
2. Upload this directory inside your `/wp-content/plugins/` directory.
3. Activate 'LCS Security' on the 'Plugins' menu in WordPress.
4. Modify options as needed in Dashboard / LCS Security / Options page.

== Frequently Asked Questions ==
= Will this slow down my site? =

No.  This plugin is extremely light and fast and adds virtually zero overhead processing.

= Can this plugin co-exist with other security plugins? =

We strongly suggest using only one security plugin and disabling all others, otherwise you run the risk of conflicts and unpredictable site behavior.

= What are the optimal settings for this plugin? =

Unless you experience problems, you can leave the options at default settings.

= I use Jetpack Forms, and started having issues with them after installing this plugin.  What should I do? =

Disable XML RPC protection on the LCS Security Options page.

= I'm having issues with another plugin not working properly after installing this plugin.  What should I do? =

Disable WP-INCLUDES malicious script blocking on the LCS Security Options page.

= Does this plugin perform virus scanning and cleaning? =

Not at this time.  If your site is already infected, we suggest restoring from a clean backup and then installing this security plugin to prevent future infections.

= Does this plugin protect against DDOS attacks? =

No.  DDOS is best handled by specialized firewalls or cloud service providers such as CloudFlare and Amazon Web Services.  Please check with your hosting service to see what options you have available for your site.

== Screenshots ==

1. Options page.
2. Options - continued.

== Changelog ==

= 2.5 =
* Fixed Excel export not working on some configurations.

= 2.4 =
* Bug fixes.

= 2.3 =
* Improvements to Excel export.

= 2.2 =
* Internal improvements.
* Reorganize code structure.

= 2.1 =
* Fix log Excel export bug.

= 2.0 =
* Performance improvements.
* Minor bug fix.

= 1.9 =
* Set newly added default options during update.
* Minor bug fix.

= 1.8 =
* Add option to disable code editing within wp-admin.

= 1.7 =
* Fully disable XMLRPC in addition to just authenitcated functions to prevent XMLRPC brute force attacks.

= 1.6 =
* Removed dependency on obsolete MCRYPT library to support PHP 7.2 and above.

= 1.5 =
* Added blocking of JSON endpoint author scanning.

= 1.4 =
* Improved handling of author enumeration scans.

= 1.3 =
* Improved handling of timed lockouts.

= 1.2 =
* Improved compatibility with PHP versions earlier than 5.5.

= 1.1 =
* Modified display of locked IP list to recalculate based on lockout minutes parameter setting.
* Added more search fields to log.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 1.3 =
* Improved handling of timed lockouts.

= 1.2 =
* Improved compatibility with PHP versions earlier than 5.5.

= 1.1 =
* Modified display of locked IP list to recalculate based on lockout minutes parameter setting.
* Added more search fields to log.

= 1.0 =
* Initial release.

== Support ==
* [sysdev@latcomsystems.com](mailto:sysdev@latcomsystems.com)
