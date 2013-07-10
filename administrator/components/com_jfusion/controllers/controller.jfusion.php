<?php

/**
 * This is the jfusion admin controller
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   ControllerAdmin
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Load the JFusion framework
 */
jimport('joomla.application.component.controller');
jimport('joomla.application.component.view');
require_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'defines.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.factory.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusion.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.jfusionadmin.php';
/**
 * JFusion Controller class
 *
 * @category  JFusion
 * @package   ControllerAdmin
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class JFusionController extends JControllerLegacy
{
    /**
     * @param bool $cachable
     * @param bool $urlparams
     *
     * @return JController|void
     */
    function display($cachable = false, $urlparams = false) {
        parent::display();
    }

    /**
     * Display the results of the wizard set-up
     *
     * @return void
     */
    function wizardresult()
    {
        //set jname as a global variable in order for elements to access it.
        global $jname;
        //find out the submitted values
        $jname = JFactory::getApplication()->input->get('jname');
        $post = JFactory::getApplication()->input->post->get('params', array());
        //check to see data was posted
        $msg = JText::_('WIZARD_FAILURE');
        $msgType = 'warning';
        if ($jname && $post) {
            //Initialize the forum
            $JFusionPlugin = JFusionFactory::getAdmin($jname);
            $params = $JFusionPlugin->setupFromPath($post['source_path']);
            if (!empty($params)) {
                //save the params first in order for elements to utilize data
                JFusionFunctionAdmin::saveParameters($jname, $params, true);

                //make sure the usergroup params are available on first view
                $config_status = $JFusionPlugin->checkConfig();
                $db = JFactory::getDBO();
                $query = 'UPDATE #__jfusion SET status = ' . $config_status['config'] . ' WHERE name =' . $db->Quote($jname);
                $db->setQuery($query);
	            try {
		            $db->execute();

		            $msg = JText::_('WIZARD_SUCCESS');
		            $msgType = 'notice';
	            } catch (Exception $e) {
		            $msg = $e->getMessage();
		            $msgType = 'notice';
	            }
            }
            $this->setRedirect('index.php?option=com_jfusion&task=plugineditor&jname=' . $jname, $msg, $msgType);
        } else {
            $this->setRedirect('index.php?option=com_jfusion&task=plugindisplay', $msg, $msgType);
        }
    }

    /**
     * Function to change the master/slave/encryption settings in the jos_jfusion table
     *
     * @return void
     */
    function changesettings()
    {
        //find out the posted ID of the JFusion module to publish
        $jname = JFactory::getApplication()->input->get('jname');
        $field_name = JFactory::getApplication()->input->get('field_name');
        $field_value = JFactory::getApplication()->input->get('field_value');
        //check to see if an integration was selected
        $db = JFactory::getDBO();
        if ($jname) {
            if ($field_name == 'master') {
                //If a master is being set make sure all other masters are disabled first
                $query = 'UPDATE #__jfusion SET master = 0';
                $db->setQuery($query);
	            $db->execute();
            }
            //perform the update
            $query = 'UPDATE #__jfusion SET ' . $field_name . ' =' . $db->Quote($field_value) . ' WHERE name = ' . $db->Quote($jname);
            $db->setQuery($query);
            $db->execute();
            //get the new plugin settings
            $query = 'SELECT * FROM #__jfusion WHERE name = ' . $db->Quote($jname);
            $db->setQuery($query);
            $result = $db->loadObject();
            //disable a slave when it is turned into a master
            if ($field_name == 'master' && $field_value == '1' && $result->slave == '1') {
                $query = 'UPDATE #__jfusion SET slave = 0 WHERE name = ' . $db->Quote($jname);
                $db->setQuery($query);
                $db->execute();
            }
            //disable a master when it is turned into a slave
            if ($field_name == 'slave' && $field_value == '1' && $result->master == '1') {
                $query = 'UPDATE #__jfusion SET master = 0 WHERE name = ' . $db->Quote($jname);
                $db->setQuery($query);
                $db->execute();
            }
            //auto enable the auth and dual login for newly enabled plugins
            if (($field_name == 'slave' || $field_name == 'master') && $field_value == '1') {
                $query = 'SELECT dual_login FROM #__jfusion WHERE name = ' . $db->Quote($jname);
                $db->setQuery($query);
                $dual_login = $db->loadResult();
                if ($dual_login > 1) {
                    //only set the encryption if dual login is disabled
                    $query = 'UPDATE #__jfusion SET check_encryption = 1 WHERE name = ' . $db->Quote($jname);
                    $db->setQuery($query);
                    $db->execute();
                } else {
                    $query = 'UPDATE #__jfusion SET dual_login = 1, check_encryption = 1 WHERE name = ' . $db->Quote($jname);
                    $db->setQuery($query);
                    $db->execute();
                }
            }
            //auto disable the auth and dual login for newly disabled plugins
            if (($field_name == 'slave' || $field_name == 'master') && $field_value == '0') {
                //only set the encryption if dual login is disabled
                $query = 'UPDATE #__jfusion SET check_encryption = 0, dual_login = 0 WHERE name = ' . $db->Quote($jname);
                $db->setQuery($query);
                $db->execute();
            }
        }

	    $result = array();
	    /**
	     * @ignore
	     * @var $view jfusionViewplugindisplay
	     */
	    $view = $this->getView('plugindisplay','html');
	    $plugins = $view->getPlugins();
	    $result['pluginlist'] = $view->generateListHTML($plugins);

	    $result['messages'] = JFusionFunction::renderMessage();
        die(json_encode($result));
    }

    /**
     * Function to save the JFusion plugin parameters
     *
     * @return void
     */
    function saveconfig()
    {
        //set jname as a global variable in order for elements to access it.
        global $jname;
        //get the posted variables
        $post = JFactory::getApplication()->input->post->get('params', array());
        $jname = JFactory::getApplication()->input->post->getString('jname', '');
        //check for trailing slash in URL, in order for us not to worry about it later
        if (substr($post['source_url'], -1) == '/') {
        } else {
            $post['source_url'].= '/';
        }
        //now also check to see that the url starts with http:// or https://
        if (substr($post['source_url'], 0, 7) != 'http://' && substr($post['source_url'], 0, 8) != 'https://') {
            if (substr($post['source_url'], 0, 1) != '/') {
                $post['source_url'] = 'http://' . $post['source_url'];
            }
        }
        if (!empty($post['source_path'])) {
            if (!is_dir($post['source_path'])) {
                JFusionFunction::raiseWarning(500, JText::_('SOURCE_PATH_NOT_FOUND'));
            }
        }
        if (!JFusionFunctionAdmin::saveParameters($jname, $post)) {
            $msg = $jname . ': ' . JText::_('SAVE_FAILURE');
            $msgType = 'error';
        } else {
            //update the status field
            $JFusionPlugin = JFusionFactory::getAdmin($jname);
            $config_status = $JFusionPlugin->checkConfig();
            $db = JFactory::getDBO();
            $query = 'UPDATE #__jfusion SET status = ' . $config_status['config'] . ' WHERE name =' . $db->Quote($jname);
            $db->setQuery($query);
            $db->execute();
            if (empty($config_status['config'])) {
                $msg = $jname . ': ' . $config_status['message'];
                $msgType = 'error';
            } else {
                $msg = $jname . ': ' . JText::_('SAVE_SUCCESS');
                $msgType = 'message';
                //check for any custom commands
                $customcommand = JFactory::getApplication()->input->get('customcommand');
                if (!empty($customcommand)) {
                    $JFusionPlugin = JFusionFactory::getAdmin($jname);
                    if (method_exists($JFusionPlugin, $customcommand)) {
                        $JFusionPlugin->$customcommand();
                    }
                }
            }
        }
        $action = JFactory::getApplication()->input->get('action');
        if ($action == 'apply') {
            $this->setRedirect('index.php?option=com_jfusion&task=plugineditor&jname=' . $jname, $msg, $msgType);
        } else {
            $this->setRedirect('index.php?option=com_jfusion&task=plugindisplay', $msg, $msgType);
        }
    }

    /**
     * Resumes a usersync if it has stopped
     *
     * @return void
     */
    function syncresume()
    {
        $syncid = JFactory::getApplication()->input->get->get('syncid', '');
        $db = JFactory::getDBO();
        $query = 'SELECT syncid FROM #__jfusion_sync WHERE syncid =' . $db->Quote($syncid);
        $db->setQuery($query);

	    $syncdata = array();
	    $syncdata['errors'] = array();
        if ($db->loadResult()) {
            //Load usersync library
            include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.usersync.php';
            $syncdata = JFusionUsersync::getSyncdata($syncid);
	        $syncdata['errors'] = array();
	        if (is_array($syncdata)) {
		        //start the usersync
		        $plugin_offset = (!empty($syncdata['plugin_offset'])) ? $syncdata['plugin_offset'] : 0;
		        //start at the next user
		        $user_offset = (!empty($syncdata['user_offset'])) ? $syncdata['user_offset'] : 0;
		        if (JFactory::getApplication()->input->get('userbatch')) {
			        $syncdata['userbatch'] = JFactory::getApplication()->input->get('userbatch');
		        }
		        JFusionUsersync::syncExecute($syncdata, $syncdata['action'], $plugin_offset, $user_offset);
	        } else {
		        $msg = JText::_('SYNC_FAILED_TO_LOAD_SYNC_DATA');
		        $syncdata['errors'][] = $msg;
		        JFusionFunction::raiseError(0, $msg);
	        }
        } else {
	        $msg = JText::sprintf('SYNC_ID_NOT_EXIST', $syncid);
            $syncdata['errors'][] = $msg;
	        JFusionFunction::raiseError(0, $msg);
        }

	    $syncdata['messages'] = JFusionFunction::renderMessage();
        die(json_encode($syncdata));
    }

    /**
     * sync process
     *
     * @return void
     */
    function syncprogress()
    {
        $syncid = JFactory::getApplication()->input->get->get('syncid', '');
        include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.usersync.php';
        $syncdata = JFusionUsersync::getSyncdata($syncid);

	    $syncdata['messages'] = JFusionFunction::renderMessage();
        die(json_encode($syncdata));
    }

    /**
     * Displays the usersync error screen
     *
     * @return void
     */
    function syncerror()
    {
        //Load usersync library
        include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.usersync.php';
        $syncError = JFactory::getApplication()->input->post->get('syncError', array());
        $syncid = JJFactory::getApplication()->input->post->get('syncid', '');
        if ($syncError) {
            //apply the submitted sync error instructions
            JFusionUsersync::syncError($syncid, $syncError);
        } else {
            //output the sync errors to the user
	        JFactory::getApplication()->input->get('view', 'syncerror');
            $this->display();
        }
    }

    /**
     * Displays the usersync history screen
     *
     * @return void
     */
    function syncerrordetails()
    {
        //Load usersync library
        include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.usersync.php';

        $view = $this->getView('syncerrordetails', 'html');
        $view->setLayout('default');
        //$result = $view->loadTemplate();
        $result = $view->display();
        die($result);
    }

    /**
     * Initiates the sync
     *
     * @return void
     */
    function syncinitiate()
    {
        //Load usersync library
        include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.usersync.php';
        //check to see if the sync has already started
        $syncid = JFactory::getApplication()->input->get('syncid');
        $action = JFactory::getApplication()->input->get('action');
        if (!empty($syncid)) {
            //clear sync in progress catch in case we manually stopped the sync so that the sync will continue
            JFusionUsersync::changeSyncStatus($syncid, 0);
        }
        $syncdata = array();
        $syncdata['completed'] = false;
        $syncdata['sync_errors'] = 0;
        $syncdata['total_to_sync'] = 0;
        $syncdata['synced_users'] = 0;
        $syncdata['userbatch'] = JFactory::getApplication()->input->get('userbatch', 100);
        $syncdata['user_offset'] = 0;
        $syncdata['syncid'] = $syncid;
        $syncdata['action'] = $action;

        $db = JFactory::getDBO();
        $query = 'SELECT syncid FROM #__jfusion_sync WHERE syncid =' . $db->Quote($syncid);
        $db->setQuery($query);
        if (!$db->loadResult()) {
            //sync has not started, lets get going :)
            $slaves = JFactory::getApplication()->input->get('slave');
            $master_plugin = JFusionFunction::getMaster();
            $master = $master_plugin->name;
            $JFusionMaster = JFusionFactory::getAdmin($master);
            //initialise the slave data array
            $slave_data = array();
            if (empty($slaves)) {
	            JFusionFunction::raiseError(0, JText::_('SYNC_NODATA'));
            } else {
                //lets find out which slaves need to be imported into the Master
                foreach ($slaves as $jname => $slave) {
                    if ($slave['perform_sync']) {
                        $temp_data = array();
                        $temp_data['jname'] = $jname;
                        $JFusionPlugin = JFusionFactory::getAdmin($jname);
                        if ($action == 'master') {
                            $temp_data['total'] = $JFusionPlugin->getUserCount();
                        } else {
                            $temp_data['total'] = $JFusionMaster->getUserCount();
                        }
                        $syncdata['total_to_sync']+= $temp_data['total'];
                        //this doesn't change and used by usersync when limiting the number of users to grab at a time
                        $temp_data['total_to_sync'] = $temp_data['total'];
                        $temp_data['created'] = 0;
                        $temp_data['deleted'] = 0;
                        $temp_data['updated'] = 0;
                        $temp_data['error'] = 0;
                        $temp_data['unchanged'] = 0;
                        //save the data
                        $slave_data[] = $temp_data;
                        //reset the variables
                        unset($temp_data, $JFusionPlugin);
                    }
                }
                //format the syncdata for storage in the JFusion sync table
                $syncdata['master'] = $master;
                $syncdata['slave_data'] = $slave_data;
                //save the submitted syncdata in order for AJAX updates to work
                JFusionUsersync::saveSyncdata($syncdata);
                //start the usersync
                JFusionUsersync::syncExecute($syncdata, $action, 0, 0);
            }
        } else {
	        JFusionFunction::raiseError(0, JText::_('SYNC_CANNOT_START'));
        }

	    $syncdata['messages'] = JFusionFunction::renderMessage();
        die(json_encode($syncdata));
    }

    /**
     * Function to upload, parse & install JFusion plugins
     *
     * @return void
     */
    function installplugin()
    {
        include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.install.php';
        $model = new JFusionModelInstaller();
        $result = $model->install();

        $ajax = JFactory::getApplication()->input->get('ajax');
        if ($ajax == true) {
	        /**
	         * @ignore
	         * @var $view jfusionViewplugindisplay
	         */
	        $view = $this->getView('plugindisplay','html');
	        $plugins = $view->getPlugins();
	        $result['pluginlist'] = $view->generateListHTML($plugins);

	        $result['messages'] = JFusionFunction::renderMessage();
            die(json_encode($result));
        } else {
            $this->setRedirect('index.php?option=com_jfusion&task=plugindisplay');
        }
    }

    function installplugins()
    {
        $jfusionplugins = JFactory::getApplication()->input->post->get('jfusionplugins', array());
        include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.install.php';
        foreach ($jfusionplugins as $plugin) {
            //install updates
            $packagename = JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'jfusion_' . $plugin . '.zip';
            $model = new JFusionModelInstaller();
            $result = $model->installZIP($packagename);
        }
        $this->setRedirect('index.php?option=com_jfusion&task=plugindisplay');
    }

    function plugincopy()
    {
        $jname = JFactory::getApplication()->input->get('jname');
        $new_jname = JFactory::getApplication()->input->get('new_jname');

        //replace not-allowed characters with _
        $new_jname = preg_replace('/([^a-zA-Z0-9_])/', '_', $new_jname);

        //initialise response element
        $result = array();

        //check to see if an integration was selected
        $db = JFactory::getDBO();
        $query = 'SELECT count(*) from #__jfusion WHERE original_name IS NULL && name LIKE '.$db->quote($jname);
        $db->setQuery($query);
        $record = $db->loadResult();
        if ($jname && $new_jname && $record) {
	        $JFusionPlugin = JFusionFactory::getAdmin($jname);
	        if ($JFusionPlugin->multiInstance()) {
		        include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.install.php';
		        $model = new JFusionModelInstaller();
		        $result = $model->copy($jname, $new_jname);

		        //get description
		        $plugin_xml = JFUSION_PLUGIN_PATH .DIRECTORY_SEPARATOR. $jname .DIRECTORY_SEPARATOR. 'jfusion.xml';
		        if(file_exists($plugin_xml) && is_readable($plugin_xml)) {
			        $xml = JFusionFunction::getXml($plugin_xml);

			        $description = $xml->description;
			        if(!empty($description)) {
				        $description = (string)$description;
			        }
		        }
		        if ($result['status']) {
			        $result['new_jname'] =  $new_jname;
		        }
	        } else {
		        $result['status'] = false;
		        JFusionFunction::raiseError(0, JText::_('CANT_COPY'));
	        }
        } else {
            $result['status'] = false;
	        JFusionFunction::raiseError(0, JText::_('NONE_SELECTED'));
        }

	    /**
	     * @ignore
	     * @var $view jfusionViewplugindisplay
	     */
	    $view = $this->getView('plugindisplay','html');
	    $plugins = $view->getPlugins();
	    $result['pluginlist'] = $view->generateListHTML($plugins);

	    $result['messages'] = JFusionFunction::renderMessage();
        //output results
        die(json_encode($result));
    }

    /**
     * Function to uninstall JFusion plugins
     *
     * @return void
     */
    function uninstallplugin()
    {
        $jname = JFactory::getApplication()->input->get('jname');

        //set uninstall options
        $db = JFactory::getDBO();
        $query = 'SELECT count(*) from #__jfusion WHERE original_name LIKE '. $db->Quote($jname);
        $db->setQuery($query);
        $copys = $db->loadResult();

        //check to see if an integration was selected
        if ($jname && $jname != 'joomla_int' && !$copys) {
            include_once JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'model.install.php';
            $model = new JFusionModelInstaller();
            $result = $model->uninstall($jname);
        } else {
	        JFusionFunction::raiseError(0, 'JFusion ' . JText::_('PLUGIN') . ' ' . JText::_('UNINSTALL') . ' ' . JText::_('FAILED'));
            $result['status'] = false;
        }

	    $result['messages'] = JFusionFunction::renderMessage();
        $result['jname'] = $jname;
        //output results
        die(json_encode($result));
    }

    /**
     * Enables the JFusion Plugins
     *
     * @return void
     */
    function enableplugins()
    {
        //enable the JFusion login behaviour, but we wanna make sure there is at least 1 master with good config
        $db = JFactory::getDBO();
        $query = 'SELECT count(*) from #__jfusion WHERE master = 1 and status = 1';
        $db->setQuery($query);
        if ($db->loadResult()) {
            JFusionFunctionAdmin::changePluginStatus('joomla','authentication',0);
            JFusionFunctionAdmin::changePluginStatus('joomla','user',0);
            JFusionFunctionAdmin::changePluginStatus('jfusion','authentication',1);
            JFusionFunctionAdmin::changePluginStatus('jfusion','user',1);
        } else {
            JFusionFunction::raiseWarning(500, JText::_('NO_MASTER_WARNING'));
        }
        $this->setRedirect('index.php?option=com_jfusion&task=cpanel');
    }

    /**
     * Disables the JFusion Plugins
     *
     * @return void
     */
    function disableplugins()
    {
        //restore the normal login behaviour
        JFusionFunctionAdmin::changePluginStatus('joomla','authentication',1);
        JFusionFunctionAdmin::changePluginStatus('joomla','user',1);
        JFusionFunctionAdmin::changePluginStatus('jfusion','authentication',0);
        JFusionFunctionAdmin::changePluginStatus('jfusion','user',0);
        $this->setRedirect('index.php?option=com_jfusion&task=cpanel');
    }

    /**
     * Config dump
     *
     * @return void
     */
    function configdump()
    {
	    JFactory::getApplication()->input->set('view', 'configdump');
        $this->display();
    }

    /**
     * delete sync history
     *
     * @return void
     */
    function deletehistory()
    {
        $db = JFactory::getDBO();
        $syncid = JFactory::getApplication()->input->get('syncid');
        if(!is_array($syncid)) {
            JFusionFunction::raiseWarning(500, JText::_('NO_SYNCID_SELECTED'));
        } else {
            foreach ($syncid as $key => $value) {
                $query = 'DELETE FROM #__jfusion_sync WHERE syncid = ' . $db->Quote($key);
                $db->setQuery($query);
                $db->execute();

                $query = 'DELETE FROM #__jfusion_sync_details WHERE syncid = ' . $db->Quote($key);
                $db->setQuery($query);
                $db->execute();
            }
        }
	    JFactory::getApplication()->input->set('view', 'synchistory');
        $this->display();
    }

    /**
     * resolve error
     *
     * @return void
     */
    function resolveerror()
    {
        $db = JFactory::getDBO();
        $syncid = JFactory::getApplication()->input->get('syncid');
        if(!is_array($syncid)) {
            JFusionFunction::raiseWarning(500, JText::_('NO_SYNCID_SELECTED'));
	        JFactory::getApplication()->input->set('view', 'synchistory');
        } else {
            foreach ($syncid as $key => $value) {
                JFactory::getApplication()->input->set('syncid', $key);
                //output the sync errors to the user
	            JFactory::getApplication()->input->set('view', 'syncerror');
                break;
            }
        }
        $this->display();
    }

    /**
     * Displays the JFusion PluginMenu Parameters
     *
     * @return void
     */
	function advancedparamsubmit()
	{
		$params = JFactory::getApplication()->input->get('params');
		$ename = JFactory::getApplication()->input->get('ename');

		$multiselect = JFactory::getApplication()->input->get('multiselect');
		if ($multiselect) {
			$multiselect = true;
		} else {
			$multiselect = false;
		}

		$serParam = base64_encode(serialize($params));

		$session = JFactory::getSession();
		$hash = JFactory::getApplication()->input->get($ename);
		$session->set($hash, $serParam);

		$title = '';
		if (isset($params['jfusionplugin'])) {
			$title = $params['jfusionplugin'];
		} else if ($multiselect) {
			$del = '';
			if (is_array($params)) {
				foreach ($params as $key => $value) {
					if (isset($value['jfusionplugin'])) {
						$title.= $del . $value['jfusionplugin'];
						$del = '; ';
					}
				}
			}
		}
		if (empty($title)) {
			$title = JText::_('NO_PLUGIN_SELECTED');
		}
		$js = '<script type="text/javascript">';
		$js .= <<<JS
            window.parent.jAdvancedParamSet('{$title}', '{$serParam}','{$ename}');
JS;
		$js .= '</script>';
		echo $js;
	}

    function saveorder()
    {
        //split the value of the sort action
        $sort_order = JFactory::getApplication()->input->get('sort_order');
        $ids = explode('|',$sort_order);
        $db = JFactory::getDBO();

        $result = array('status' => true, 'messages' => '');
        /* run the update query for each id */
        foreach($ids as $index=>$id)
        {
            if($id != '') {
                $query = 'UPDATE #__jfusion SET ordering = ' .(int) $index .' WHERE name = ' . $db->Quote($id);
	            $db->setQuery($query);

	            try {
		            $db->execute();
	            } catch (RuntimeException $e) {
		            JFusionFunction::raiseError(0,$e->getMessage());
		            $result['status'] = false;
	            }
            }
        }
	    /**
	     * @ignore
	     * @var $view jfusionViewplugindisplay
	     */
	    $view = $this->getView('plugindisplay','html');
	    $plugins = $view->getPlugins();
	    $result['pluginlist'] = $view->generateListHTML($plugins);

	    $result['messages'] = JFusionFunction::renderMessage();
        die(json_encode($result));
    }

    function import()
    {
        $jname = JFactory::getApplication()->input->get('jname');

        $msg = $xml = $error = null;

	    jimport('joomla.utilities.simplexml');
	    $file = JFactory::getApplication()->input->files->get('file');

	    $filename = JFactory::getApplication()->input->get('url');

	    if( !empty($filename) ) {
		    $filename = base64_decode($filename);
		    $ConfigFile = JFusionFunctionAdmin::getFileData($filename);
		    if (!empty($ConfigFile)) {
			    $xml = JFusionFunction::getXml($ConfigFile,false);
		    }
	    } else if( $file['error'] > 0 ) {
		    switch ($file['error']) {
			    case UPLOAD_ERR_INI_SIZE:
				    $error = JText::_('UPLOAD_ERR_INI_SIZE');
				    break;
			    case UPLOAD_ERR_FORM_SIZE:
				    $error = JText::_('UPLOAD_ERR_FORM_SIZE');
				    break;
			    case UPLOAD_ERR_PARTIAL:
				    $error = JText::_('UPLOAD_ERR_PARTIAL');
				    break;
			    case UPLOAD_ERR_NO_FILE:
				    $error = JText::_('UPLOAD_ERR_NO_FILE');
				    break;
			    case UPLOAD_ERR_NO_TMP_DIR:
				    $error = JText::_('UPLOAD_ERR_NO_TMP_DIR');
				    break;
			    case UPLOAD_ERR_CANT_WRITE:
				    $error = JText::_('UPLOAD_ERR_CANT_WRITE');
				    break;
			    case UPLOAD_ERR_EXTENSION:
				    $error = JText::_('UPLOAD_ERR_EXTENSION');
				    break;
			    default:
				    $error = JText::_('UNKNOWN_UPLOAD_ERROR');
		    }
		    $error = $jname . ': ' . JText::_('ERROR').': '.$error;
	    } else {
		    $filename = $file['tmp_name'];
		    $xml = JFusionFunction::getXml($filename);
	    }
	    if(!$xml) {
		    $error = $jname . ': ' . JText::_('ERROR_LOADING_FILE').': '.$filename;
	    } else {
		    /**
		     * @ignore
		     * @var $val JXMLElement
		     */
		    $info = $config = null;
		    foreach ($xml->children() as $key => $val) {
			    switch ($val->name()) {
				    case 'info':
					    $info = $val;
					    break;
				    case 'config':
					    $config = $val->children();
					    break;
			    }
		    }

		    if (!$info || !$config) {
			    JFusionFunction::raiseWarning(0, $jname . ': ' . JText::_('ERROR_FILE_SYNTAX').': '.$file['type'] );
			    $error = $jname . ': ' . JText::_('ERROR_FILE_SYNTAX').': '.$file['type'];
		    } else {
			    $original_name = (string)$info->attributes('original_name');
			    $db = JFactory::getDBO();
			    $query = 'SELECT name , original_name from #__jfusion WHERE name = ' . $db->Quote($jname);
			    $db->setQuery($query);
			    $plugin = $db->loadObject();

			    if ($plugin) {
				    $pluginname = $plugin->original_name ? $plugin->original_name : $plugin->name;
				    if ($pluginname == $original_name) {
					    $conf = array();
					    /**
					     * @ignore
					     * @var $val JXMLElement
					     */
					    foreach ($config as $key => $val) {
						    $attName = (string)$val->attributes('name');
						    $conf[$attName] = htmlspecialchars_decode((string)$val);
						    if ( strpos($conf[$attName], 'a:') === 0 ) $conf[$attName] = unserialize($conf[$attName]);
					    }

					    $database_type = JFactory::getApplication()->input->get('database_type');
					    $database_host = JFactory::getApplication()->input->get('database_host');
					    $database_name = JFactory::getApplication()->input->get('database_name');
					    $database_user = JFactory::getApplication()->input->get('database_user');
					    $database_password = JFactory::getApplication()->input->get('database_password');
					    $database_prefix = JFactory::getApplication()->input->get('database_prefix');

					    if( !empty($database_type) ) $conf['database_type'] = $database_type;
					    if( !empty($database_host) ) $conf['database_host'] = $database_host;
					    if( !empty($database_name) ) $conf['database_name'] = $database_name;
					    if( !empty($database_user) ) $conf['database_user'] = $database_user;
					    if( !empty($database_password) ) $conf['database_password'] = $database_password;
					    if( !empty($database_prefix) ) $conf['database_prefix'] = $database_prefix;

					    if (!JFusionFunctionAdmin::saveParameters($jname, $conf)) {
						    $error = $jname . ': ' . JText::_('SAVE_FAILURE');
					    } else {
						    //update the status field
						    $JFusionPlugin = JFusionFactory::getAdmin($jname);
						    $config_status = $JFusionPlugin->checkConfig();
						    $db = JFactory::getDBO();
						    $query = 'UPDATE #__jfusion SET status = ' . $config_status['config'] . ' WHERE name =' . $db->Quote($jname);
						    $db->setQuery($query);
						    $db->execute();
						    if (empty($config_status['config'])) {
							    $error = $jname . ': ' . $config_status['message'];
						    } else {
							    $msg = $jname . ': ' . JText::_('IMPORT_SUCCESS');
						    }
					    }
				    } else {
					    $error = $jname.': '.JText::_('PLUGIN_DONT_MATCH_XMLFILE');
				    }
			    } else {
				    $error = $jname.': '.JText::_('PLUGIN_NOT_FOUNED');
			    }
		    }
	    }
        $mainframe = JFactory::getApplication();
        if ($error) {
            JFusionFunction::raiseWarning(0, $error );
            $mainframe->redirect('index.php?option=com_jfusion&task=importexport&jname='.$jname);
        } else {
            $mainframe->redirect('index.php?option=com_jfusion&task=plugineditor&jname='.$jname,$msg);
        }
        exit();
    }

    function export()
    {
        $jname = JFactory::getApplication()->input->get('jname');
        $dbinfo = JFactory::getApplication()->input->get('dbinfo');

        $params = JFusionFactory::getParams($jname);
        $params = $params->toObject();
        jimport('joomla.utilities.simplexml');

        $arr = array();
        foreach ($params as $key => $val) {
            if( !$dbinfo && substr($key,0,8) == 'database' && substr($key,0,13) != 'database_type' ) {
                continue;
            }
            $arr[$key] = $val;
        }

	    $xml = JFusionFunction::getXml('<jfusionconfig></jfusionconfig>',false);

        /**
         * @ignore
         * @var $info JXMLElement
         */
        $info = $xml->addChild('info');

        list($VersionCurrent,$RevisionCurrent) = JFusionFunctionAdmin::currentVersion(true);

        $info->addAttribute  ('jfusionversion',  $VersionCurrent);
        $info->addAttribute  ('jfusionrevision',  $RevisionCurrent);

        //get the current JFusion version number
        $filename = JFUSION_PLUGIN_PATH .DIRECTORY_SEPARATOR.$jname.DIRECTORY_SEPARATOR.'jfusion.xml';
        if (file_exists($filename) && is_readable($filename)) {
            //get the version number
	        $element = JFusionFunction::getXml($filename);

            $info->addAttribute('pluginversion', (string)$element->version);
        } else {
            $info->addAttribute('pluginversion', 'UNKNOWN');
        }

        $info->addAttribute('date', date('F j, Y, H:i:s'));

        $info->addAttribute  ('jname', $jname);

        $db = JFactory::getDBO();
        $query = 'SELECT original_name FROM #__jfusion WHERE name =' . $db->Quote($jname);
        $db->setQuery($query);
        $original_name = $db->loadResult();

	    $original_name = $original_name ? $original_name : $jname;

        $info->addAttribute  ('original_name', $original_name);

        /**
         * @ignore
         * @var $info JXMLElement
         * @var $config JXMLElement
         * @var $node JXMLElement
         */
        $config = $xml->addChild('config');
        foreach ($arr as $key => $val) {
            $attrs = array();
            $attrs['name'] = $key;
            $node = $config->addChild('key',$attrs);
            if (is_array($val)) $val = serialize($val);
            $node->setData($val);
        }

        header('Content-disposition: attachment; filename=jfusion_'.$jname.'_config.xml');
        header('content-type: text/xml');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $xml->toString();
        exit();
    }
}
