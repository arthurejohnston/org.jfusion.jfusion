<?php

/**
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

//force required variables into global scope
use JFusion\Api\Platform;

if (!isset($GLOBALS['vbulletin']) && !empty($vbulletin)) {
    $GLOBALS['vbulletin'] = & $vbulletin;
}
if (!isset($GLOBALS['db']) && !empty($db)) {
    $GLOBALS['db'] = & $db;
}

/**
 * Vbulletin hook class
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage vBulletin
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class executeJFusionJoomlaHook
{
    var $vars;

    /**
     * @param $hook
     * @param $vars
     * @param string $key
     */
    function __construct($hook, &$vars, $key = '')
    {
        if ($hook != 'init_startup' && !defined('_VBJNAME') && empty($_POST['logintype'])) {
            die('JFusion plugins need to be updated.  Reinstall desired plugins in JFusions config for vBulletin.');
        }

        if (!defined('_JFVB_PLUGIN_VERIFIED') && $hook != 'init_startup' && defined('_VBJNAME') && defined('_JEXEC') && empty($_POST['logintype'])) {
            define('_JFVB_PLUGIN_VERIFIED', 1);
	        $user = \JFusion\Factory::getUser(_VBJNAME);
            if (!$user->isConfigured()) {
                die('JFusion plugin is invalid.  Reinstall desired plugins in JFusions config for vBulletin.');
            }
        }

        //execute the hook
        $this->vars =& $vars;
        $this->key = $key;
        eval('$success = $this->' . $hook . '();');
        //if ($success) die('<pre>'.print_r($GLOBALS['vbulletin']->pluginlist, true).'</pre>');
    }

    function init_startup()
    {
        global $vbulletin;
        if ($this->vars == 'redirect' && !isset($_GET['noredirect']) && !defined('_JEXEC') && !isset($_GET['jfusion'])) {
            //only redirect if in the main forum
            if (!empty($_SERVER['PHP_SELF'])) {
                $s = $_SERVER['PHP_SELF'];
            } elseif (!empty($_SERVER['SCRIPT_NAME'])) {
                $s = $_SERVER['SCRIPT_NAME'];
            } else {
                //the current URL cannot be determined so abort redirect
                return;
            }
            $ignore = array($vbulletin->config['Misc']['admincpdir'], 'ajax.php', 'archive', 'attachment.php', 'cron.php', 'image.php', 'inlinemod', 'login.php', 'misc.php', 'mobiquo', $vbulletin->config['Misc']['modcpdir'], 'newattachment.php', 'picture.php', 'printthread.php', 'sendmessage.php');
            if (defined('REDIRECT_IGNORE')) {
                $custom_files = explode(',', REDIRECT_IGNORE);
                if (is_array($custom_files)) {
                    foreach ($custom_files as $file) {
                        if (!empty($file)) {
                            $ignore[] = trim($file);
                        }
                    }
                }
            }
            $redirect = true;
            foreach ($ignore as $i) {
                if (strpos($s, $i) !== false) {
                    //for sendmessage.php, only redirect if not sending an IM
                    if ($i == 'sendmessage.php') {
                        $do = $_GET['do'];
                        if ($do != 'im') {
                            continue;
                        }
                    }
                    $redirect = false;
                    break;
                }
            }

            if (isset($_POST['jfvbtask'])) {
                $redirect = false;
            }

            if ($redirect && defined('JOOMLABASEURL')) {
                $filename = basename($s);
                $query = $_SERVER['QUERY_STRING'];
                if (defined('SEFENABLED') && SEFENABLED) {
                    if (defined('SEFMODE') && SEFMODE == 1) {
                        $url = JOOMLABASEURL . $filename . '/';
                        if (!empty($query)) {
                            $q = explode('&', $query);
                            foreach ($q as $k => $v) {
                                $url.= $k . ',' . $v . '/';
                            }
                        }
                        if (!empty($query)) {
                            $queries = explode('&', $query);
                            foreach ($queries as $q) {
                                $part = explode('=', $q);
                                $url.= $part[0] . ',' . $part[1] . '/';
                            }
                        }
                    } else {
                        $url = JOOMLABASEURL . $filename;
                        $url.= (empty($query)) ? '' : '?' . $query;
                    }
                } else {
                    $url = JOOMLABASEURL . '&jfile=' . $filename;
                    $url.= (empty($query)) ? '' : '&' . $query;
                }
                header('Location: ' . $url);
                exit;
            }
        }
        //add our custom hooks into vbulletin hook cache
        if (!empty($vbulletin->pluginlist) && is_array($vbulletin->pluginlist)) {
            $hooks = $this->getHooks($this->vars);
            if (is_array($hooks)) {
                foreach ($hooks as $name => $code) {
                    if ($name == 'global_setup_complete') {
                        $depracated =  (version_compare($vbulletin->options['templateversion'], '4.0.2') >= 0) ? 1 : 0;
                        if ($depracated) {
                            $name = 'global_bootstrap_complete';
                        }
                    }

                    if (isset($vbulletin->pluginlist[$name])) {
                        $vbulletin->pluginlist[$name] .= "\n$code";
                    } else {
                        $vbulletin->pluginlist[$name] = $code;
                    }
                }
            }
        }
    }

    /**
     * @param $plugin
     * @return array
     */
    function getHooks($plugin)
    {
        global $hookFile;

        if (empty($hookFile) && defined('JFUSION_VB_JOOMLA_HOOK_FILE')) {
            //as of JFusion 1.6
            $hookFile = JFUSION_VB_JOOMLA_HOOK_FILE;
        }

        //we need to set up the hooks
        if ($plugin == 'duallogin') {
            //retrieve the hooks that vBulletin will use to login to Joomla
            $hookNames = array('global_setup_complete', 'login_verify_success', 'logout_process');
            define('DUALLOGIN', 1);
        } else {
            $hookNames = array();
        }
        $hooks = array();

        foreach ($hookNames as $h) {
            //certain hooks we want to call directly such as global variables
            if ($h == 'profile_editoptions_start') {
                $hooks[$h] = 'global $stylecount;';
            } else {
                if ($h == 'album_picture_complete') $toPass = '$vars =& $pictureinfo; ';
                elseif ($h == 'global_complete') $toPass = '$vars =& $output; ';
                elseif ($h == 'header_redirect') $toPass = '$vars =& $url;';
                elseif ($h == 'member_profileblock_fetch_unwrapped') $toPass = '$vars =& $prepared;';
                elseif ($h == 'redirect_generic') $toPass = '$vars = array(); $vars["url"] =& $url; $vars["js_url"] =& $js_url; $vars["formfile"] =& $formfile;';
                elseif ($h == 'xml_print_output') $toPass = '$vars = & $this->doc;';
                else $toPass = '$vars = null;';
                $hooks[$h] = 'include_once \'' . $hookFile . '\'; ' . $toPass . ' $jFusionHook = new executeJFusionJoomlaHook(\'' . $h . '\', $vars, \''. $this->key . '\');';
            }
        }
        return $hooks;
    }
    /**
     * HOOK FUNCTIONS
     *
     * @return bool
     */
    function album_picture_complete()
    {
        global $vbulletin;
        $start = strpos($this->vars['pictureurl'], '/picture.php');
        $tempURL = $vbulletin->options['bburl'] . substr($this->vars['pictureurl'], $start);
        $this->vars['pictureurl'] = $tempURL;
        return true;
    }
    /**
     * global_complete
     *
     * @throws Exception
     *
     * @return void
     */

    function global_complete()
    {
        if (defined('_JEXEC')) {
            global $vbulletin;
            //create cookies to allow direct login into vb frameless
            /*
            if ($vbulletin->userinfo['userid'] != 0 && empty($vbulletin->GPC[COOKIE_PREFIX . 'userid'])) {
                if ($vbulletin->GPC['cookieuser']) {
                    $expire = 60 * 60 * 24 * 365;
                } else {
                    $expire = 0;
                }
                $cookies = \JFusion\Factory::getCookies();
                $cookies->addCookie(COOKIE_PREFIX . 'userid', $vbulletin->userinfo['userid'], $expire, $vbulletin->options['cookiepath'], $vbulletin->options['cookiedomain'], false, true);
                $cookies->addCookie(COOKIE_PREFIX . 'password', md5($vbulletin->userinfo['password'] . COOKIE_SALT), $expire, $vbulletin->options['cookiepath'], $vbulletin->options['cookiedomain'], false, true);
            }
            */
            //we need to update the session table
	        if (defined('_VBJNAME')) {
		        try {
			        $vdb = \JFusion\Factory::getDatabase(_VBJNAME);
			        $vars = & $vbulletin->session->vars;
			        if ($vbulletin->session->created) {
				        $bypass = ($vars['bypass']) ? 1 : 0;

				        $query = 'INSERT IGNORE INTO #__session
                            ( sessionhash, userid, host, idhash, lastactivity, location, styleid, languageid, loggedin, inforum, inthread, incalendar, badlocation, useragent, bypass, profileupdate )
                            VALUES ( ' .
					            $vdb->quote($vars['dbsessionhash']) .
					            ' ,' . $vars['userid'] .
					            ' ,' . $vdb->quote($vars['host']) .
					            ' ,' . $vdb->quote($vars['idhash']) .
					            ' ,' . $vars['lastactivity'] .
					            ' ,' . $vdb->quote($vars['location']) .
					            ' ,' . $vars['styleid'] .
					            ' ,' . $vars['languageid'] .
					            ' ,' . $vars['loggedin'] .
					            ' ,' . $vars['inforum'] .
					            ' ,' . $vars['inthread'] .
					            ' ,' . $vars['incalendar'] .
					            ' ,' . $vars['badlocation'] .
					            ' ,' . $vdb->quote($vars['useragent']) .
					            ' ,' . $bypass .
					            ' ,' . $vars['profileupdate'] .
					        ' )';
			        } else {
				        $query = $vdb->getQuery(true)
					        ->update('#__session')
					        ->set('lastactivity = ' . $vdb->quote($vars['lastactivity']))
					        ->set('inforum = ' . $vdb->quote($vars['inforum']))
					        ->set('inthread = ' . $vdb->quote($vars['inthread']))
					        ->set('incalendar = ' . $vdb->quote($vars['incalendar']))
					        ->set('badlocation = ' . $vdb->quote($vars['badlocation']))
					        ->where('sessionhash = ' . $vdb->quote($vars['dbsessionhash']));
			        }
			        $vdb->setQuery($query);
			        $vdb->execute();
			        //we need to perform the shutdown queries that mark PMs read, etc
			        if (is_array($vbulletin->db->shutdownqueries)) {
				        foreach ($vbulletin->db->shutdownqueries AS $name => $query) {
					        if (!empty($query) AND ($name !== 'pmpopup' OR !defined('NOPMPOPUP'))) {
						        $vdb->setQuery($query);
						        $vdb->execute();
					        }
				        }
			        }
		        } catch (Exception $e) {
		        }
		        //echo the output and return an exception to allow Joomla to continue
		        echo trim($this->vars, "\n\r\t.");
		        Throw new RuntimeException('vBulletin exited.');
	        } else {
		        Throw new RuntimeException('vBulletin exited. _VBJNAME not defined');
	        }
        }
    }

    /**
     * @return bool
     */
    function global_setup_complete()
    {
        if (defined('_JEXEC')) {
            //If Joomla SEF is enabled, the dash in the logout hash gets converted to a colon which must be corrected
            global $vbulletin, $show, $vbsefenabled, $vbsefmode;
            $vbulletin->GPC['logouthash'] = str_replace(':', '-', $vbulletin->GPC['logouthash']);
            //if sef is enabled, we need to rewrite the nojs link
            if ($vbsefenabled == 1) {
                if ($vbsefmode == 1) {
                    $uri = JUri::getInstance();
                    $url = $uri->toString();
                    $show['nojs_link'] = $url;
                    $show['nojs_link'].= (substr($url, -1) != '/') ? '/nojs,1/' : 'nojs,1/';
                } else {
	                $jfile = \JFusion\Factory::getApplication()->input->get('jfile', false);
                    $jfile = ($jfile) ? $jfile : 'index.php';
                    $show['nojs_link'] = $jfile . '?nojs=1';
                }
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    function global_start()
    {
        //lets rewrite the img urls now while we can
        global $stylevar, $vbulletin;
        //check for trailing slash
        $DS = (substr($vbulletin->options['bburl'], -1) == '/') ? '' : '/';
        if(!empty($stylevar)) {
            foreach ($stylevar as $k => $v) {
                if (strstr($k, 'imgdir') && strstr($v, $vbulletin->options['bburl']) === false && strpos($v, 'http') === false) {
                    $stylevar[$k] = $vbulletin->options['bburl'] . $DS . $v;
                }
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    function header_redirect()
    {
        global $vbsefenabled, $vbsefmode, $baseURL, $integratedURL, $foruminfo, $vbulletin;
        //reworks the URL for header redirects ie header('Location: $url');
        //if this is a forum link, return without parsing the URL
        if (!empty($foruminfo['link']) && (THIS_SCRIPT != 'subscription' || $_REQUEST['do'] != 'removesubscription')) {
            return false;
        }
        if (defined('_JFUSION_DEBUG')) {
            $debug = array();
            $debug['url'] = $this->vars;
            $debug['function'] = 'header_redirect';
        }
        $admincp = & $vbulletin->config['Misc']['admincpdir'];
        $modcp = & $vbulletin->config['Misc']['modcp'];
        //create direct URL for admincp, modcp, and archive
        if (strpos($this->vars, $admincp) !== false || strpos($this->vars, $modcp) !== false || strpos($this->vars, 'archive') !== false) {
            if (defined('_JFUSION_DEBUG')) {
                $debug['parsed'] = $this->vars;
                $_SESSION['jfvbdebug'][] = $debug;
            }
            if (!empty($vbsefenabled)) {
                if ($vbsefmode == 1) {
                    $pos = '';
                    if (strpos($this->vars, $admincp) !== false) {
                        $pos = $admincp;
                    } elseif (strpos($this->vars, $modcp) !== false) {
                        $pos = $modcp;
                    } elseif (strpos($this->vars, 'archive') !== false) {
                        $pos = 'archive';
                    }
                    $this->vars = $integratedURL . substr($this->vars, strpos($this->vars, $pos));
                } else {
                    $this->vars = str_replace($baseURL, $integratedURL, $this->vars);
                }
            } else {
                $this->vars = str_replace(\JFusionFunction::getJoomlaURL(), $integratedURL, $this->vars);
            }
            //convert &amp; to & so the redirect is correct
            $this->vars = str_replace('&amp;', '&', $this->vars);
            return true;
        }
        //let's make sure the baseURL does not have a / at the end for comparison
        $testURL = (substr($baseURL, -1) == '/') ? substr($baseURL, 0, -1) : $baseURL;
        if (strpos(strtolower($this->vars['url']), strtolower($testURL)) === false) {
            $url = basename($this->vars);
            $url = JFusionFunction::routeURL($url, \JFusion\Factory::getApplication()->input->getInt('Itemid'));
            $this->vars = $url;
        }
        //convert &amp; to & so the redirect is correct
        $this->vars = str_replace('&amp;', '&', $this->vars);

        if (defined('_JFUSION_DEBUG')) {
            $debug['parsed'] = $this->vars;
            $_SESSION['jfvbdebug'][] = $debug;
        }
        return true;
    }

    /**
     * @return bool
     */
    function login_verify_success()
    {
        $this->backup_restore_globals('backup');
        global $vbulletin;
        //if JS is enabled, only a hashed form of the password is available
        $password = (!empty($vbulletin->GPC['vb_login_password'])) ? $vbulletin->GPC['vb_login_password'] : $vbulletin->GPC['vb_login_md5password'];
        if (!empty($password)) {
            if (!defined('_JEXEC')) {
                $mainframe = $this->startJoomla();
            } else {
                $mainframe = JFactory::getApplication('site');
                define('_VBULLETIN_JFUSION_HOOK', true);
            }
            // do the login
            global $JFusionActivePlugin;
	        if (defined('_VBJNAME')) {
		        $JFusionActivePlugin =  _VBJNAME;
	        }
            $baseURL = (class_exists('JFusionFunction')) ? \JFusionFunction::getJoomlaURL() : JUri::root();
            $loginURL = JRoute::_($baseURL . 'index.php?option=com_user&task=login', false);
            $credentials = array('username' => $vbulletin->userinfo['username'], 'password' => $password, 'password_salt' => $vbulletin->userinfo['salt']);
            $options = array('entry_url' => $loginURL);
            //set remember me option
            if(!empty($vbulletin->GPC['cookieuser'])) {
                $options['remember'] = 1;
            }
            //creating my own vb security string for check in the function
            define('_VB_SECURITY_CHECK', md5('jfusion' . md5($password . $vbulletin->userinfo['salt'])));
            $mainframe->login($credentials, $options);
            // clean up the joomla session object before continuing
            $session = JFactory::getSession();
            $session->close();
        }
        $this->backup_restore_globals('restore');
        return true;
    }

    /**
     * @return bool
     */
    function logout_process()
    {
        $this->backup_restore_globals('backup');
        if (defined('_JEXEC')) {
            //we are in frameless mode and need to kill the cookies to prevent getting stuck logged in
            global $vbulletin;
	        $cookies = \JFusion\Factory::getCookies();
	        $cookies->addCookie(COOKIE_PREFIX . 'userid', 0, 0, $vbulletin->options['cookiepath'], $vbulletin->options['cookiedomain'], false, true);
	        $cookies->addCookie(COOKIE_PREFIX . 'password', 0, 0, $vbulletin->options['cookiepath'], $vbulletin->options['cookiedomain'], false, true);
            //prevent global_complete from recreating the cookies
            $vbulletin->userinfo['userid'] = 0;
            $vbulletin->userinfo['password'] = 0;
        }
        if (defined('DUALLOGIN')) {
            if (!defined('_JEXEC')) {
                $mainframe = $this->startJoomla();
            } else {
                $mainframe = JFactory::getApplication('site');
                define('_VBULLETIN_JFUSION_HOOK', true);
            }
            global $JFusionActivePlugin;
	        if (defined('_VBJNAME')) {
		        $JFusionActivePlugin =  _VBJNAME;
	        }
            // logout any joomla users
            $mainframe->logout();
            // clean up session
            $session = JFactory::getSession();
            $session->close();
        }
        $this->backup_restore_globals('restore');
        return true;
    }

    /**
     * @param $action
     */
    function backup_restore_globals($action)
    {
        static $vb_globals;

        if (!is_array($vb_globals)) {
            $vb_globals = array();
        }

        if ($action == 'backup') {
            foreach ($GLOBALS as $n => $v) {
                $vb_globals[$n] = $v;
            }
        } else {
            foreach ($vb_globals as $n => $v) {
                $GLOBALS[$n] = $v;
            }
        }
    }
    function member_profileblock_fetch_unwrapped()
    {
        global $vbsefmode, $vbsefenabled, $baseURL;
        static $profileurlSet;
        if (!empty($this->vars['profileurl']) && $profileurlSet !== true) {
            $uid = \JFusion\Factory::getApplication()->input->get('u');
            if ($vbsefenabled && $vbsefmode) {
                $this->vars['profileurl'] = str_replace('member.php?u=' . $uid, '', $this->vars['profileurl']);
            } else {
                $this->vars['profileurl'] = $baseURL . '&jfile=member.php&u=' . $uid;
            }
            $profileurlSet = true;
        }
    }

    /**
     * @return bool
     */
    function redirect_generic()
    {
        global $baseURL;
        //reworks the URL for generic redirects that use JS or html meta header
        if (defined('_JFUSION_DEBUG')) {
            $debug = array();
            $debug['url'] = $this->vars['url'];
            $debug['function'] = 'redirect_generic';
        }
        //let's make sure the baseURL does not have a / at the end for comparison
        $testURL = (substr($baseURL, -1) == '/') ? substr($baseURL, 0, -1) : $baseURL;
        if (strpos(strtolower($this->vars['url']), strtolower($testURL)) === false) {
            $url = basename($this->vars['url']);
            $url = JFusionFunction::routeURL($url, \JFusion\Factory::getApplication()->input->getInt('Itemid'));

            //convert &amp; to & so the redirect is correct
            $url = str_replace('&amp;', '&', $url);
            $this->vars['url'] = $url;
            $this->vars['js_url'] = addslashes_js($this->vars['url']);
            $this->vars['formfile'] = $this->vars['url'];
        }
        if (defined('_JFUSION_DEBUG')) {
            $debug['parsed'] = $this->vars['url'];
            $_SESSION['jfvbdebug'][] = $debug;
        }
        return true;
    }

    function xml_print_output()
    {
        if (!defined('_JEXEC')) {
            $this->startJoomla();
        }

        //parse AJAX output
	    if (defined('_VBJNAME')) {
		    $public = \JFusion\Factory::getFront(_VBJNAME);
		    $params = \JFusion\Factory::getParams(_VBJNAME);

		    $jdata = new stdClass();
		    $jdata->body = & $this->vars;
		    $jdata->Itemid = $params->get('plugin_itemid');
		    //Get the base URL to the specific JFusion plugin
		    $jdata->baseURL = JFusionFunction::getPluginURL($jdata->Itemid);
		    //Get the integrated URL
		    $jdata->integratedURL = $params->get('source_url');
		    $public->parseBody($jdata);
	    }
    }

    /**
     * @return JApplication
     */
    function startJoomla()
    {
        define('_VBULLETIN_JFUSION_HOOK', true);
        define('_JFUSIONAPI_INTERNAL', true);
        require_once JPATH_BASE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR  . 'jfusionapi.php';
	    $joomla = Platform::getInstance();
	    $mainframe = $joomla->getApplication();

        $curlFile = JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_jfusion' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.curl.php';
        if (file_exists($curlFile)) {
            require_once $curlFile;
        }
        return $mainframe;
    }
}