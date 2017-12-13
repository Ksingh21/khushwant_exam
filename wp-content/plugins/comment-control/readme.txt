=== Comment Control ===
Contributors: solarissmoke
Tags: comments, default, status, control
Requires at least: 3.5
Tested up to: 4.6
Stable tag: trunk

Gives administrators some more flexible options for controlling comments - e.g., independently setting the default comment status for Posts and Pages.

== Description ==

This plugin gives administrators more flexible control of commenting than WordPress does by default. You can set the default comment status independently for different post types (Posts, Pages etc), as well as tweak a few other things like the comment status on attachments. You can also bulk edit the comment status on all your posts at one go.

If you come across any bugs or have suggestions, please use the plugin support forum or [email me](http://rayofsolaris.net/contact/). I can't fix it if I don't know it's broken!

== Changelog ==

= 0.4.1 =
* Add CSRF validation to settings page.

= 0.4 =
* Add tool buttons for bulk edit of ping status.

= 0.3.1 =
* Fix SQL injection vulnerability. Thanks to John Grimes.

= 0.3 =
* Add the option to turn off comments on all attachments.

= 0.2 =
* Add the ability to force attachments to inherit their comment status from their parent post. I've no idea why WordPress doesn't do this by default. See http://core.trac.wordpress.org/ticket/8177

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin settings can be accessed via the 'Settings' menu in the administration area
 
