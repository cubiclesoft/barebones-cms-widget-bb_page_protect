Barebones CMS - SSO Server/Client Page Protection widget
========================================================

Adds a new widget that protects a Barebones CMS page with SSO server/client integration.  Note that protected pages are not cached and therefore experience a reduction in performance.  This widget can require a user to be signed in to see the content or be configured to show different content depending on whether or not the user is signed in.  This widget may be used in conjunction with the Code widget.

Defines the following globals:

  * $g_bb_page_protect_sso_client - A standard SSO client object.
  * $g_bb_page_protect_fields - An array containing the requested fields.
  * $g_bb_page_protect_has_access - A boolean specifying whether or not the user has access.

Also available:

  * bb_protect_page::GetFormsSecretKey() - Static function that returns a secret key for integrated security token generation/validation via the Code widget (XSRF defense).
  * bb_protect_page::GetFormsExtraInfo() - Static function that returns extra information for integrated security token generation/validation via the Code widget (XSRF defense).

SSO server/client:

http://barebonescms.com/documentation/sso/

License
-------

Same as Barebones CMS.  MIT or LGPL (your choice).

Automated Installation
----------------------

To install this widget, use the built-in Barebones CMS extension installer.

The widget manages the process of correctly installing the SSO client.

Manual Installation
-------------------

Upload the 'widgets' subdirectory to your Barebones CMS installation.

The widget manages the process of correctly installing the SSO client.
