<?php
/**
 * MyAlerts alerts file - used to redirect to alerts, show alerts and more.
 */

define('IN_MYBB', true);
define('THIS_SCRIPT', 'alerts.php');

require_once __DIR__ . '/global.php';

$action = $mybb->get_input('action', MyBB::INPUT_STRING);

if (!isset($lang->myalerts)) {
    $lang->load('myalerts');
}

switch ($action) {
    case 'view':
        myalerts_redirect_alert($mybb, $lang);
        break;
	case 'settings':
		myalerts_alert_settings($mybb, $db, $lang, $plugins, $templates, $theme);
        break;
    default:
        myalerts_view_alerts($mybb, $lang, $templates, $theme);
        break;
}

/**
 * Handle a request to view a single alert by marking the alert read and forwarding on to the correct location.
 *
 * @param MyBB $mybb MyBB core object.
 * @param MyLanguage $lang Language object.
 */
function myalerts_redirect_alert($mybb, $lang)
{
    $alertId = $mybb->get_input('id', MyBB::INPUT_INT);

    /** @var MybbStuff_MyAlerts_Entity_Alert $alert */
    $alert = $GLOBALS['mybbstuff_myalerts_alert_manager']->getAlert($alertId);
    /** @var MybbStuff_MyAlerts_Formatter_AbstractFormatter $alertTypeFormatter */
    $alertTypeFormatter = $GLOBALS['mybbstuff_myalerts_alert_formatter_manager']->getFormatterForAlertType($alert->getType()->getCode());

    if (!$alert || !$alertTypeFormatter) {
        error($lang->myalerts_error_alert_not_found);
    }

    $GLOBALS['mybbstuff_myalerts_alert_manager']->markRead(array($alertId));

    $redirectLink = unhtmlentities($alertTypeFormatter->buildShowLink($alert));

    header('Location: ' . $redirectLink);
}

/**
 * Show a user their settings for MyAlerts.
 *
 * @param MyBB $mybb MyBB core object.
 * @param DB_MySQLi|DB_MySQL $db Database object.
 * @param MyLanguage $lang Language object.
 * @param pluginSystem $plugins MyBB plugin system.
 * @param templates $templates Template manager.
 * @param array $theme Details about the current theme.
 */
function myalerts_alert_settings($mybb, $db, $lang, $plugins, $templates, $theme)
{
	$alertTypes = $GLOBALS['mybbstuff_myalerts_alert_type_manager']->getAlertTypes();

	if (strtolower($mybb->request_method) == 'post') { // Saving alert type settings
		$disabledAlerts = array();

		foreach ($alertTypes as $alertCode => $alertType) {
			if (!isset($_POST[$alertCode])) {
				$disabledAlerts[] = (int) $alertType['id'];
			}
		}

		if ($disabledAlerts != $mybb->user['myalerts_disabled_alert_types']) { // Different settings, so update
			$jsonEncodedDisabledAlerts = json_encode($disabledAlerts);

			$db->update_query('users', array(
					'myalerts_disabled_alert_types' => $db->escape_string($jsonEncodedDisabledAlerts)
				), 'uid=' . (int) $mybb->user['uid']);
		}

		redirect(
			'alerts.php?action=settings',
			$lang->myalerts_settings_updated,
			$lang->myalerts_settings_updated_title
		);
	} else { // Displaying alert type settings form

		$content = '';

		global $headerinclude, $header, $footer, $usercpnav;

		add_breadcrumb($lang->myalerts_settings_page_title, 'alerts.php?action=settings');

		require_once __DIR__ . '/inc/functions_user.php';
		usercp_menu();

		foreach ($alertTypes as $key => $value) {
			if ($value['enabled']) {
				$altbg = alt_trow();
				$tempKey = 'myalerts_setting_' . $key;

				$baseSettings = array('rep', 'pm', 'buddylist', 'quoted', 'post_threadauthor');

				$plugins->run_hooks('myalerts_load_lang');

				$langline = $lang->$tempKey;

				$checked = '';
				if (!in_array($value['id'], $mybb->user['myalerts_disabled_alert_types'])) {
					$checked = ' checked="checked"';
				}

				eval("\$alertSettings .= \"" . $templates->get('myalerts_setting_row') . "\";");
			}
		}

		eval("\$content = \"" . $templates->get('myalerts_settings_page') . "\";");
		output_page($content);
	}
}

/**
 * View all alerts.
 *
 * @param MyBB $mybb MyBB core object.
 * @param MyLanguage $lang Language object.
 * @param templates $templates Template manager.
 * @param array $theme Details about the current theme.
 */
function myalerts_view_alerts($mybb, $lang, $templates, $theme)
{
    $alerts = $GLOBALS['mybbstuff_myalerts_alert_manager']->getAlerts(0, 10);

    if (!$lang->myalerts) {
        $lang->load('myalerts');
    }

    add_breadcrumb($lang->nav_usercp, 'usercp.php');
    add_breadcrumb($lang->myalerts_page_title, 'usercp.php?action=alerts');

    require_once __DIR__ . '/inc/functions_user.php';
    usercp_menu();

    $numAlerts = $GLOBALS['mybbstuff_myalerts_alert_manager']->getNumAlerts();
    $page      = (int) $mybb->input['page'];
    $pages     = ceil($numAlerts / $mybb->settings['myalerts_perpage']);

    if ($page > $pages OR $page <= 0) {
        $page = 1;
    }

    if ($page) {
        $start = ($page - 1) * $mybb->settings['myalerts_perpage'];
    } else {
        $start = 0;
        $page  = 1;
    }
    $multipage = multipage($numAlerts, $mybb->settings['myalerts_perpage'], $page, "usercp.php?action=alerts");

    $alertsList = $GLOBALS['mybbstuff_myalerts_alert_manager']->getAlerts($start);

    $readAlerts = array();

    if (is_array($alertsList) && !empty($alertsList)) {
        foreach ($alertsList as $alertObject) {
            $altbg = alt_trow();

            $alert = parse_alert($alertObject);

            if ($alert['message']) {
                eval("\$alertsListing .= \"" . $templates->get('myalerts_alert_row') . "\";");
            }

            $readAlerts[] = $alert['id'];
        }
    } else {
        $altbg = 'trow1';
        eval("\$alertsListing = \"" . $templates->get('myalerts_alert_row_no_alerts') . "\";");
    }

    $GLOBALS['mybbstuff_myalerts_alert_manager']->markRead($readAlerts);

    global $headerinclude, $header, $footer, $usercpnav;

    $content = '';
    eval("\$content = \"" . $templates->get('myalerts_page') . "\";");
    output_page($content);
}