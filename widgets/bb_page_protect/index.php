<?php
	// Barebones CMS Page Protection Widget
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("BB_FILE"))  exit();

	require_once ROOT_PATH . "/" . SUPPORT_PATH . "/bb_functions.php";

	$bb_widget->_s = "bb_page_protect";
	$bb_widget->_n = "Page Protection";
	$bb_widget->_key = "";
	$bb_widget->_ver = "";

	global $g_bb_page_protect_sso_client, $g_bb_page_protect_fields, $g_bb_page_protect_has_access;
	$g_bb_page_protect_sso_client = false;
	$g_bb_page_protect_fields = array();
	$g_bb_page_protect_has_access = false;

	// Initialize sitewide page protection options.
	global $g_bb_page_protect_options_path;
	$g_bb_page_protect_options = array();
	$g_bb_page_protect_options_path = ROOT_PATH . "/" . WIDGET_PATH . "/" . BB_GetRealPath(Str::ExtractPathname($bb_widget->_file)) . "/options.php";
	if (file_exists($g_bb_page_protect_options_path))  require_once $g_bb_page_protect_options_path;
	if (!isset($g_bb_page_protect_options["allow_remote"]))  $g_bb_page_protect_options["allow_remote"] = true;
	if (!isset($g_bb_page_protect_options["allow_impersonate"]))  $g_bb_page_protect_options["allow_impersonate"] = true;

	class bb_page_protect extends BB_WidgetBase
	{
		public static function GetFormsSecretKey()
		{
			global $bb_langpage, $g_bb_page_protect_sso_client, $g_bb_page_protect_has_access;

			return pack("H*", $bb_langpage["pagerand"]) . ($g_bb_page_protect_has_access ? ":" . $g_bb_page_protect_sso_client->GetSecretToken() : "");
		}

		public static function GetFormsExtraInfo()
		{
			global $bb_pref_lang, $g_bb_page_protect_sso_client, $g_bb_page_protect_has_access;

			return $bb_pref_lang . ($g_bb_page_protect_has_access ? ":" . $g_bb_page_protect_sso_client->GetUserID() : "");
		}

		public function Init()
		{
			global $bb_widget, $bb_page, $g_bb_page_protect_sso_client, $g_bb_page_protect_fields, $g_bb_page_protect_has_access, $g_bb_page_protect_options;

			if (!isset($bb_widget->require_login))  $bb_widget->require_login = true;
			if (!isset($bb_widget->force_load))  $bb_widget->force_load = false;
			if (!isset($bb_widget->check_perms))  $bb_widget->check_perms = false;
			if (!isset($bb_widget->site_admin))  $bb_widget->site_admin = true;
			if (!isset($bb_widget->tags))  $bb_widget->tags = array();
			if (!isset($bb_widget->fields))  $bb_widget->fields = array();

			$this->sso_client_dir = BB_GetRealPath(Str::ExtractPathname($bb_widget->_file) . "/sso_client");

			$basepath = ROOT_PATH . "/" . WIDGET_PATH . "/" . $this->sso_client_dir;
			if (file_exists($basepath . "/index.php") && file_exists($basepath . "/config.php"))
			{
				// Disable caching for any page that this widget is placed onto.
				$bb_page["cachetime"] = 0;

				if (!defined("BB_MODE_EDIT"))
				{
					require_once $basepath . "/config.php";
					require_once SSO_CLIENT_ROOT_PATH . "/index.php";

					$g_bb_page_protect_sso_client = new SSO_Client;
					$g_bb_page_protect_sso_client->Init(array("sso_impersonate", "sso_remote_id"));

					// Check/initiate login.
					$extra = array();
					if ($g_bb_page_protect_options["allow_remote"] && isset($_REQUEST["sso_remote_id"]) && is_string($_REQUEST["sso_remote_id"]))
					{
						$extra["sso_provider"] = "sso_remote";
						$extra["sso_remote_id"] = $_REQUEST["sso_remote_id"];
					}
					if ($g_bb_page_protect_options["allow_impersonate"] && isset($_REQUEST["sso_impersonate"]) && is_string($_REQUEST["sso_impersonate"]))  $extra["sso_impersonate"] = $_REQUEST["sso_impersonate"];
					if (!$g_bb_page_protect_sso_client->LoggedIn() && (count($extra) || $bb_widget->require_login))  $g_bb_page_protect_sso_client->Login("", "", $extra);

					// Only load fields and check tags if the user is logged in.
					if ($g_bb_page_protect_sso_client->LoggedIn())
					{
						// Load fields.
						if (!$g_bb_page_protect_sso_client->UserLoaded())
						{
							if ($bb_widget->force_load)
							{
								// Always load SSO server information.
								if (!$g_bb_page_protect_sso_client->LoadUserInfo())
								{
									echo "Unable to load user information.";
									exit();
								}
							}
							else
							{
								// Only load SSO server information if it isn't available locally.
								$missingfield = false;
								foreach ($bb_widget->fields as $key => $field)
								{
									$g_bb_page_protect_fields[$field] = $g_bb_page_protect_sso_client->GetData($key);
									if ($g_bb_page_protect_fields[$field] === false)  $missingfield = true;
								}

								if ($missingfield && !$g_bb_page_protect_sso_client->LoadUserInfo())
								{
									echo "Unable to load user information.";
									exit();
								}
							}
						}

						// Check to see if fields were loaded somewhere along the line and then cache locally.
						if ($g_bb_page_protect_sso_client->UserLoaded())
						{
							foreach ($bb_widget->fields as $key => $field)
							{
								$g_bb_page_protect_fields[$field] = $g_bb_page_protect_sso_client->GetField($field);

								$g_bb_page_protect_sso_client->SetData($key, $g_bb_page_protect_fields[$field]);
							}
						}

						// Send the browser cookies.
						$g_bb_page_protect_sso_client->SaveUserInfo();

						// Check permissions/tags.
						$g_bb_page_protect_has_access = !$bb_widget->check_perms;

						if ($bb_widget->site_admin && $g_bb_page_protect_sso_client->IsSiteAdmin())  $g_bb_page_protect_has_access = true;
						else
						{
							foreach ($bb_widget->tags as $tag)
							{
								if ($g_bb_page_protect_sso_client->HasTag($tag))  $g_bb_page_protect_has_access = true;
							}
						}

						if (!$g_bb_page_protect_has_access && $bb_widget->check_perms)  $g_bb_page_protect_sso_client->Login("", "insufficient_permissions");
					}
				}
			}
		}

		public function Process()
		{
			global $bb_mode, $bb_widget, $bb_widget_id, $g_bb_page_protect_has_access;

			if ($bb_mode == "head")
			{
				if (!$bb_widget->require_login)
				{
					if (defined("BB_MODE_EDIT") || $g_bb_page_protect_has_access)  BB_ProcessMasterWidget($bb_widget_id . "_logged_in");
					if (defined("BB_MODE_EDIT") || !$g_bb_page_protect_has_access)  BB_ProcessMasterWidget($bb_widget_id . "_logged_out");
				}
			}
			else if ($bb_mode == "body")
			{
				if (!$bb_widget->require_login)
				{
					if (defined("BB_MODE_EDIT") || $g_bb_page_protect_has_access)  BB_ProcessMasterWidget($bb_widget_id . "_logged_in");
					if (defined("BB_MODE_EDIT") || !$g_bb_page_protect_has_access)  BB_ProcessMasterWidget($bb_widget_id . "_logged_out");
				}
			}
		}

		public function PreWidget()
		{
			global $bb_widget, $bb_account;

			if ($bb_account["type"] == "dev")
			{
				$basepath = ROOT_PATH . "/" . WIDGET_PATH . "/" . $this->sso_client_dir;
				if (file_exists($basepath . "/index.php") && file_exists($basepath . "/config.php"))
				{
					echo BB_CreateWidgetPropertiesLink("Configure", "bb_page_protect_configure_widget");
				}
				else if (file_exists($basepath . "/index.php"))
				{
					if (file_exists($basepath . "/install.php"))  echo "<a href=\"" . htmlspecialchars(ROOT_URL . "/" . WIDGET_PATH . "/" . $this->sso_client_dir . "/install.php?cookie_name=sso_bbcms&cookie_path=" . urlencode(ROOT_URL . "/") . "&cookie_timeout=604800") . "\" target=\"_blank\">" . BB_Translate("Run SSO Client Installer") . "</a>";
					else  echo BB_Translate("SSO client installer (install.php) not found in the '%s' directory.<br />", htmlspecialchars(ROOT_URL . "/" . WIDGET_PATH . "/" . $this->sso_client_dir . "/"));
				}
				else
				{
					echo BB_Translate("<a href=\"http://barebonescms.com/documentation/sso/\" target=\"_blank\">Install SSO client to '%s'</a>", htmlspecialchars(ROOT_URL . "/" . WIDGET_PATH . "/" . $this->sso_client_dir . "/"));
				}
			}
		}

		public function ProcessBBAction()
		{
			global $bb_widget, $bb_account, $bb_revision_num, $g_bb_page_protect_options, $g_bb_page_protect_options_path;

			if ($bb_account["type"] == "dev" && $_REQUEST["bb_action"] == "bb_page_protect_configure_widget_submit")
			{
				BB_RunPluginAction("pre_bb_page_protect_configure_widget_submit");

				$bb_widget->require_login = ($_REQUEST["require_login"] == 1);
				$bb_widget->force_load = ($_REQUEST["force_load"] == 1);
				$bb_widget->check_perms = ($_REQUEST["check_perms"] == 1);
				$bb_widget->site_admin = ($_REQUEST["site_admin"] == 1);

				$bb_widget->tags = array();
				$tags = explode("\n", $_REQUEST["tags"]);
				foreach ($tags as $tag)
				{
					$tag = trim($tag);
					if ($tag != "")  $bb_widget->tags[] = $tag;
				}

				$bb_widget->fields = array();
				$fields = explode("\n", $_REQUEST["fields"]);
				foreach ($fields as $field)
				{
					$field = trim($field);
					if ($field != "")  $bb_widget->fields[] = $field;
				}

				if (!BB_SaveLangPage($bb_revision_num))  BB_PropertyFormError("Unable to save the layout activation.");

				$g_bb_page_protect_options["allow_remote"] = ($_REQUEST["allow_remote"] == 1);
				$g_bb_page_protect_options["allow_impersonate"] = ($_REQUEST["allow_impersonate"] == 1);

				// Save sitewide options.
				$data = "<" . "?php\n\t\$g_bb_page_protect_options = " . BB_CreatePHPStorageData($g_bb_page_protect_options) . ";\n?" . ">";
				if (BB_WriteFile($g_bb_page_protect_options_path, $data) === false)  BB_PropertyFormError("Unable to save the sitewide options.");

?>
<div class="success"><?php echo htmlspecialchars(BB_Translate("Configuration saved.")); ?></div>
<script type="text/javascript">
window.parent.CloseProperties();
window.parent.ReloadIFrame();
</script>
<?php

				BB_RunPluginAction("post_bb_page_protect_configure_widget_submit");
			}
			else if ($bb_account["type"] == "dev" && $_REQUEST["bb_action"] == "bb_page_protect_configure_widget")
			{
				BB_RunPluginAction("pre_bb_page_protect_configure_widget");

				$options = array(
					"title" => BB_Translate("Configure %s", $bb_widget->_f),
					"desc" => "Select the various page protection options.",
					"fields" => array(
						array(
							"title" => "Require Login?",
							"type" => "select",
							"name" => "require_login",
							"options" => array("1" => "Yes", "0" => "No"),
							"select" => (string)(int)$bb_widget->require_login,
							"desc" => "Require the user to login to see this page."
						),
						array(
							"title" => "Force Load?",
							"type" => "select",
							"name" => "force_load",
							"options" => array("0" => "No", "1" => "Yes"),
							"select" => (string)(int)$bb_widget->force_load,
							"desc" => "Force the SSO client to always contact the SSO server for the latest user information.  Negatively affects page performance."
						),
						array(
							"title" => "Check Permissions/Tags?",
							"type" => "select",
							"name" => "check_perms",
							"options" => array("1" => "Yes", "0" => "No"),
							"select" => (string)(int)$bb_widget->check_perms,
							"desc" => "Checks the user's permissions/tags against the next two options."
						),
						array(
							"title" => "Check Permissions/Tags - Site Admin",
							"type" => "select",
							"name" => "site_admin",
							"options" => array("1" => "Yes", "0" => "No"),
							"select" => (string)(int)$bb_widget->site_admin,
							"desc" => "Allows the Site Admin to have access."
						),
						array(
							"title" => "Check Permissions/Tags - User Tags",
							"type" => "textarea",
							"name" => "tags",
							"value" => implode("\n", $bb_widget->tags),
							"desc" => "Allow users with any of the specified tags to have access.  One tag per line."
						),
						array(
							"title" => "User Fields",
							"type" => "textarea",
							"name" => "fields",
							"value" => implode("\n", $bb_widget->fields),
							"desc" => "The fields to extract from the SSO client into the global \$g_bb_page_protect_fields array.  One field per line."
						),
						array(
							"title" => "Sitewide - Allow Remotes?",
							"type" => "select",
							"name" => "allow_remote",
							"options" => array("1" => "Yes", "0" => "No"),
							"select" => (string)(int)$g_bb_page_protect_options["allow_remote"],
							"desc" => "Sitewide option to allow/enable remote provider integration for this SSO client.  See the SSO server/client documentation for details."
						),
						array(
							"title" => "Sitewide - Allow Impersonation?",
							"type" => "select",
							"name" => "allow_impersonate",
							"options" => array("1" => "Yes", "0" => "No"),
							"select" => (string)(int)$g_bb_page_protect_options["allow_impersonate"],
							"desc" => "Sitewide option to allow/enable impersonation support for this SSO client.  See the SSO server/client documentation for details."
						),
					),
					"submit" => "Save",
					"focus" => true
				);

				BB_RunPluginActionInfo("bb_page_protect_configure_widget_options", $options);

				BB_PropertyForm($options);

				BB_RunPluginAction("post_bb_page_protect_configure_widget");
			}
		}
	}
?>