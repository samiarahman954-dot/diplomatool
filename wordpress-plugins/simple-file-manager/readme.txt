=== Simple File Manager ===
Tags: file manager, files, upload, admin
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight file manager for the WordPress admin.

== Description ==

Adds a "File Manager" menu to the WordPress admin where administrators can:

* Browse folders
* Upload one or more files
* Create folders
* Rename files and folders
* Download files
* Delete files and folders

= Security =

* Only users with the `manage_options` capability (administrators) can use it.
* Every action is protected by a WordPress nonce.
* All paths are confined to the root directory; `../` tricks and symlinks
  pointing outside the root are rejected.
* Uploads and renames only allow file types WordPress itself allows
  (so `.php`, `.phtml`, `.htaccess` and similar are blocked).

= Root directory =

By default the manager works inside `wp-content/uploads`. To change it, add
this to `wp-config.php`:

    define( 'SFM_ROOT_DIR', '/absolute/path/to/folder' );

or use the `sfm_root_dir` filter.

== Installation ==

1. In WordPress go to Plugins > Add New > Upload Plugin.
2. Choose `simple-file-manager.zip` and click Install Now.
3. Activate the plugin.
4. Open "File Manager" in the admin menu.
