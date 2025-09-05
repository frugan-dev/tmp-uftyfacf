=== Upload Field to YouTube for ACF ===
Contributors: Frugan
Tags: acf, fields, youtube, upload, video, custom-fields
Requires at least: 5.6.0
Tested up to: 6.8
Stable tag: 0.4.0
Requires PHP: 8.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://buymeacoff.ee/frugan

A powerful ACF field that allows direct YouTube video uploads and playlist-based video selection with comprehensive management features.

== Description ==

**Upload Field to YouTube for ACF** is a WordPress plugin that adds a new custom field type to Advanced Custom Fields, enabling you to:

* **Upload videos directly to YouTube** from the WordPress admin via Google API
* **Select existing videos** from your YouTube channel organized by playlists  
* **Manage unlisted/private videos** perfect for membership sites or exclusive content
* **Choose upload modes**: client-side (browser → YouTube) or server-side (browser → WordPress → YouTube)

Perfect for content creators, course platforms, membership sites, and any WordPress site that needs seamless YouTube integration.

= Key Features =

* Direct YouTube API integration with OAuth authentication
* Support for both upload and selection workflows
* Configurable privacy settings (public, unlisted, private)
* Advanced logging system with Wonolog support
* Clean dependency injection architecture
* Vanilla JavaScript implementation (no jQuery dependency)
* Full ACF nested repeater support
* Multilingual ready with Crowdin translations

= Requirements =

* Advanced Custom Fields plugin (5.9+ or 6.0+)
* Google API credentials (OAuth 2.0)
* PHP 8.0 or higher

= Use Cases =

* **Online Courses**: Upload course videos directly from lesson creation
* **Membership Sites**: Manage private/unlisted content for members
* **Content Management**: Organize videos by playlists and embed selectively
* **Corporate Sites**: Streamlined video content workflow

For detailed installation instructions, configuration options, and developer documentation, please visit the [GitHub repository](https://github.com/frugan-dev/upload-field-to-youtube-for-acf).

== Installation ==

1. Install and activate the Advanced Custom Fields plugin
2. Install this plugin through the WordPress plugins screen or upload manually
3. Set up Google OAuth credentials in your wp-config.php (see GitHub documentation)
4. Create ACF fields using the new "YouTube Uploader" field type
5. Configure field settings according to your needs

For complete setup instructions including Google API configuration, visit: https://github.com/frugan-dev/upload-field-to-youtube-for-acf#configuration

== Frequently Asked Questions ==

= Do I need a Google API key? =

Yes, you need to create OAuth 2.0 credentials in the Google API Console. Detailed setup instructions are available in our GitHub documentation.

= Can I upload large video files? =

Yes! The plugin uses resumable uploads and supports both client-side and server-side upload modes to handle files of any size.

= Does this work with ACF Repeater fields? =

Absolutely! The plugin fully supports nested repeaters and complex field structures.

= What video formats are supported? =

All major video formats supported by YouTube: MP4, AVI, MOV, WMV, FLV, WebM, MKV, and more.

= Is this compatible with my caching plugin? =

Yes, the plugin includes comprehensive cache compatibility features for all major WordPress caching solutions.

== Screenshots ==

1. YouTube Uploader field configuration in ACF
2. Upload interface in post editor
3. Video selection from playlists
4. Plugin settings page

== Changelog ==

= 0.4.0 =
Release date: TBD

For detailed changelog and release notes, visit: https://github.com/frugan-dev/upload-field-to-youtube-for-acf/blob/master/CHANGELOG.md

== Upgrade Notice ==

= 0.4.0 =
Major update with improved caching, logging, and upload reliability. Review configuration options after update.

== Support ==

* GitHub Issues: https://github.com/frugan-dev/upload-field-to-youtube-for-acf/issues
* Documentation: https://github.com/frugan-dev/upload-field-to-youtube-for-acf/blob/master/README.md

== Development ==

This plugin follows WordPress coding standards and is actively maintained on GitHub. Contributions welcome!

* Repository: https://github.com/frugan-dev/upload-field-to-youtube-for-acf
* Roadmap: https://github.com/frugan-dev/upload-field-to-youtube-for-acf/projects
* Translations: https://crowdin.com/project/upload-field-to-youtube-for-acf