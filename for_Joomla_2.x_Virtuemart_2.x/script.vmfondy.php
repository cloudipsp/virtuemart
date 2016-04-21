<?php
defined('_JEXEC') or die('Restricted access');

/**
 * VirtueMart script file
 *
 * This file is executed during install/upgrade and uninstall
 *
 * @author Patrick Kohl, Max Milbers
 * @package VirtueMart
 */

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

// hack to prevent defining these twice in 1.6 installation
if (!defined('_VM_SCRIPT_INCLUDED')) {

	define('_VM_SCRIPT_INCLUDED', true);


	class com_VirtueMart_fondy_pluginInstallerScript {

		public function preflight(){
			//$this->vmInstall();
		}

		public function install(){
			//$this->vmInstall();
		}

		public function discover_install(){
			//$this->vmInstall();
		}

		public function postflight () {
			$this->vmInstall();
		}

		public function vmInstall () {

			jimport('joomla.filesystem.file');
			jimport('joomla.installer.installer');


			$this->createIndexFolder(JPATH_ROOT .DS. 'plugins'.DS.'vmpayment');


			$this->path = JInstaller::getInstance()->getPath('extension_administrator');

			$this->updateShipperToShipment();
			$this->installPlugin('VM - Payment, Fondy', 'plugin','fondy', 'vmpayment');

			// language auto move
			$src= $this->path .DS."languageBE" ;
			$dst= JPATH_ADMINISTRATOR . DS . "language" ;
			$this->recurse_copy( $src ,$dst );
			//echo " VirtueMart2 language   moved to the joomla language BE folder   <br/ >" ;


			echo "<H3>Installing Virtuemart Fondy payment plugin success.</h3>";
			echo "<H3>You may directly uninstall this component. Your plugin will remain</h3>";
			echo "<H3>You may create new payment method as open this path in bach end Joomla menu Components->VirtueMart->Payment methods->Add new, change the payment method option to \"WM - Payment, Fondy\", configure the created payment method and save it.</h3>";
			echo "<H3>Ignore the message ".JText::_('JLIB_INSTALLER_ABORT_COMP_BUILDADMINMENUS_FAILED')."</h3>";

			return true;

		}

		/**
		 * Installs a vm plugin into the database
		 *
		 */
		private function installPlugin($name, $type, $element, $group){

			$data = array();

			if(version_compare(JVERSION,'1.7.0','ge')) {

				// Joomla! 1.7 code here
				$table = JTable::getInstance('extension');
				$data['enabled'] = 1;
				$data['access']  = 1;
				$tableName = '#__extensions';
				$idfield = 'extension_id';
			} elseif(version_compare(JVERSION,'1.6.0','ge')) {

				// Joomla! 1.6 code here
				$table = JTable::getInstance('extension');
				$data['enabled'] = 1;
				$data['access']  = 1;
				$tableName = '#__extensions';
				$idfield = 'extension_id';
			} else {

				// Joomla! 1.5 code here
				$table = JTable::getInstance('plugin');
				$data['published'] = 1;
				$data['access']  = 0;
				$tableName = '#__plugins';
				$idfield = 'id';
			}

			$data['name'] = $name;
			$data['type'] = $type;
			$data['element'] = $element;
			$data['folder'] = $group;

			$data['client_id'] = 0;


			$src= $this->path .DS. 'plugins' .DS. $group .DS.$element;

			if(version_compare(JVERSION,'1.6.0','ge')) {
				$data['manifest_cache'] = json_encode(JApplicationHelper::parseXMLInstallFile($src.DS.$element.'.xml'));
			}

			$db = JFactory::getDBO();
			$q = 'SELECT '.$idfield.' FROM `'.$tableName.'` WHERE `name` = "'.$name.'" ';
			$db->setQuery($q);
			$count = $db->loadResult();

			if(!empty($count)){
				$table->load($count);
			}

			if(!$table->bind($data)){
				$app = JFactory::getApplication();
				$app -> enqueueMessage('VMInstaller table->bind throws error for '.$name.' '.$type.' '.$element.' '.$group);
			}

			if(!$table->check($data)){
				$app = JFactory::getApplication();
				$app -> enqueueMessage('VMInstaller table->check throws error for '.$name.' '.$type.' '.$element.' '.$group);

			}

			if(!$table->store($data)){
				$app = JFactory::getApplication();
				$app -> enqueueMessage('VMInstaller table->store throws error for '.$name.' '.$type.' '.$element.' '.$group);
			}

			$errors = $table->getErrors();
			foreach($errors as $error){
				$app = JFactory::getApplication();
				$app -> enqueueMessage( get_class( $this ).'::store '.$error);
			}


			if(version_compare(JVERSION,'1.7.0','ge')) {
				// Joomla! 1.7 code here
				$dst= JPATH_ROOT . DS . 'plugins' .DS. $group.DS.$element;

			} elseif(version_compare(JVERSION,'1.6.0','ge')) {
				// Joomla! 1.6 code here
				$dst= JPATH_ROOT . DS . 'plugins' .DS. $group.DS.$element;
			} else {
				// Joomla! 1.5 code here
				$dst= JPATH_ROOT . DS . 'plugins' .DS. $group;
			}

			$this->recurse_copy( $src ,$dst );


		}

		public function installModule($title,$module,$ordering,$params){

			$params = '';

			$table = JTable::getInstance('module');
			if(version_compare(JVERSION,'1.7.0','ge')) {
				// Joomla! 1.7 code here
				// 			$table = JTable::getInstance('module');
				$data['position'] = 'position-4';
				$data['access']  = $access = 1;
			} elseif(version_compare(JVERSION,'1.6.0','ge')) {
				// Joomla! 1.6 code here
				// 			$table = JTable::getInstance('module');
				$data['position'] ='left';
				$data['access']  = $access = 1;
			} else {
				// Joomla! 1.5 code here
				$data['position'] = 'left';
				$data['access']  = $access = 0;
			}

			$src= JPATH_ROOT .DS. 'modules' .DS. $module ;
			if(version_compare(JVERSION,'1.6.0','ge')) {
				$data['manifest_cache'] = json_encode(JApplicationHelper::parseXMLInstallFile($src.DS.$module.'.xml'));
			}
			$data['title'] 	= $title;
			$data['ordering'] = $ordering;
			$data['published'] = 1;
			$data['module'] 	= $module;
			$data['params'] 	= $params;

			$data['client_id'] = $client_id = 0;

			$db = $table->getDBO();
			$q = 'SELECT id FROM `#__modules` WHERE `title` = "'.$title.'" ';
			$db->setQuery($q);
			$id = $db->loadResult();
			if(!empty($id)){
				$data['id'] = $id;
			}
			// 			if(empty($count)){
			if(!$table->bind($data)){
				$app = JFactory::getApplication();
				$app -> enqueueMessage('VMInstaller table->bind throws error for '.$title.' '.$module.' '.$params);
			}

			if(!$table->check($data)){
				$app = JFactory::getApplication();
				$app -> enqueueMessage('VMInstaller table->check throws error for '.$title.' '.$module.' '.$params);

			}

			if(!$table->store($data)){
				$app = JFactory::getApplication();
				$app -> enqueueMessage('VMInstaller table->store throws error for for '.$title.' '.$module.' '.$params);
			}

			$errors = $table->getErrors();
			foreach($errors as $error){
				$app = JFactory::getApplication();
				$app -> enqueueMessage( get_class( $this ).'::store '.$error);
			}
			// 			}

			$lastUsedId = $table->id;

			$q = 'SELECT moduleid FROM `#__modules_menu` WHERE `moduleid` = "'.$lastUsedId.'" ';
			$db->setQuery($q);
			$moduleid = $db->loadResult();

			$action = '';
			if(empty($moduleid)){
				$q = 'INSERT INTO `#__modules_menu` (`moduleid`, `menuid`) VALUES( "'.$lastUsedId.'" , "0");';
			} else {
				$q = 'UPDATE `#__modules_menu` SET `menuid`= "0" WHERE `moduleid`= "'.$moduleid.'" ';
			}
			$db->setQuery($q);
			$db->query();

			if(version_compare(JVERSION,'1.6.0','ge')) {

				$q = 'SELECT extension_id FROM `#__extensions` WHERE `element` = "'.$module.'" ';
				$db->setQuery($q);
				$ext_id = $db->loadResult();

				//				$manifestCache = str_replace('"', '\'', $data["manifest_cache"]);
				$action = '';
				if(empty($ext_id)){
					$q = 'INSERT INTO `#__extensions` 	(`name`, `type`, `element`, `folder`, `client_id`, `enabled`, `access`, `protected`, `manifest_cache`, `params`, `ordering`) VALUES
																	( "'.$module.'" , "module", "'.$module.'", "", "0", "1","'.$access.'", "0", "'.$db->getEscaped($data["manifest_cache"]).'", "'.$params.'","'.$ordering.'");';
				} else {
					$q = 'UPDATE `#__extensions` SET 	`name`= "'.$module.'",
																	`type`= "module",
																	`element`= "'.$module.'",
																	`folder`= "",
																	`client_id`= "'.$client_id.'",
																	`enabled`= "1",
																	`access`= "'.$access.'",
																	`protected`= "0",
																	`manifest_cache` = "'.$db->getEscaped($data["manifest_cache"]).'",
																	`ordering`= "'.$ordering.'"

					WHERE `extension_id`= "'.$ext_id.'" ';
				}
				$db->setQuery($q);
				if(!$db->query()){
					$app = JFactory::getApplication();
					$app -> enqueueMessage( get_class( $this ).'::  '.$db->getErrorMsg());
				}

			}
		}

		/**
		 * @author Max Milbers
		 * @param string $tablename
		 * @param string $fields
		 * @param string $command
		 */
		private function alterTable($tablename,$fields,$command='CHANGE'){

			if(empty($this->db)){
				$this->db = JFactory::getDBO();
			}

			$query = 'SHOW COLUMNS FROM `'.$tablename.'` ';
			$this->db->setQuery($query);
			$columns = $this->db->loadResultArray(0);

			foreach($fields as $fieldname => $alterCommand){
				if(in_array($fieldname,$columns)){
					$query = 'ALTER TABLE `'.$tablename.'` '.$command.' COLUMN `'.$fieldname.'` '.$alterCommand;

					$this->db->setQuery($query);
					$this->db->query();
				}
			}


		}

		/**
		 *
		 * @author Max Milbers
		 * @param string $table
		 * @param string $field
		 * @param string $fieldType
		 * @return boolean This gives true back, WHEN it altered the table, you may use this information to decide for extra post actions
		 */
		private function checkAddFieldToTable($table,$field,$fieldType){

			$query = 'SHOW COLUMNS FROM `'.$table.'` ';
			$this->db->setQuery($query);
			$columns = $this->db->loadResultArray(0);

			if(!in_array($field,$columns)){


				$query = 'ALTER TABLE `'.$table.'` ADD '.$field.' '.$fieldType;
				$this->db->setQuery($query);
				if(!$this->db->query()){
					$app = JFactory::getApplication();
					$app->enqueueMessage('Install checkAddFieldToTable '.$this->db->getErrorMsg() );
					return false;
				} else {
					return true;
				}
			}
			return false;
		}

		private function addToRequired($table,$fieldname,$fieldvalue,$insert){
			if(empty($this->db)){
				$this->db = JFactory::getDBO();
			}

			$query = 'SELECT * FROM `'.$table.'` WHERE '.$fieldname.' = "'.$fieldvalue.'" ';
			$this->db->setQuery($query);
			$result = $this->db->loadResult();
			if(empty($result) || !$result ){
				$this->db->setQuery($insert);
				if(!$this->db->query()){
					$app = JFactory::getApplication();
					$app->enqueueMessage('Install addToRequired '.$this->db->getErrorMsg() );
				}
			}

		}

		private function updateShipperToShipment()  {
			if(empty($this->db)){
				$this->db = JFactory::getDBO();
			}
			if(version_compare(JVERSION,'1.6.0','ge')) {
				// Joomla! 1.6 code here
				$table = JTable::getInstance('extension');
				$tableName = '#__extensions';
				$idfield = 'extension_id';
			} else {

				// Joomla! 1.5 code here
				$table = JTable::getInstance('plugin');
				$tableName = '#__plugins';
				$idfield = 'id';
			}

			$q = 'SELECT '.$idfield.' FROM '.$tableName.' WHERE `folder` = "vmshipper" ';
			$this->db->setQuery($q);
			$result = $this->db->loadResult();
			if($result){
				$q = 'UPDATE `'.$tableName.'` SET `folder`="vmshipment" WHERE `extension_id`= '.$result;
				$this->db->setQuery($q);
				$this->db->query();
			}
		}
		/**
		 * copy all $src to $dst folder and remove it
		 *
		 * @author Max Milbers
		 * @param String $src path
		 * @param String $dst path
		 * @param String $type modules, plugins, languageBE, languageFE
		 */
		private function recurse_copy($src,$dst ) {

			$dir = opendir($src);
			$this->createIndexFolder($dst);

			if(is_resource($dir)){
				while(false !== ( $file = readdir($dir)) ) {
					if (( $file != '.' ) && ( $file != '..' )) {
						if ( is_dir($src .DS. $file) ) {
							$this->recurse_copy($src .DS. $file,$dst .DS. $file);
						}
						else {
							if(JFile::exists($dst .DS. $file)){
								if(!JFile::delete($dst .DS. $file)){
									$app = JFactory::getApplication();
									$app -> enqueueMessage('Couldnt delete '.$dst .DS. $file);
								}
							}
							if(!JFile::move($src .DS. $file,$dst .DS. $file)){
								$app = JFactory::getApplication();
								$app -> enqueueMessage('Couldnt move '.$src .DS. $file.' to '.$dst .DS. $file);
							}
						}
					}
				}
				closedir($dir);
				if (is_dir($src)) JFolder::delete($src);
			} else {
				$app = JFactory::getApplication();
				$app -> enqueueMessage('Couldnt read dir '.$dir.' source '.$src);
			}

		}


		public function uninstall() {

			return true;
		}

		/**
		 * creates a folder with empty html file
		 *
		 * @author Max Milbers
		 *
		 */
		public function createIndexFolder($path){

			if(JFolder::create($path)) {
				if(!JFile::exists($path .DS. 'index.html')){
					JFile::copy(JPATH_ROOT.DS.'components'.DS.'index.html', $path .DS. 'index.html');
				}
				return true;
			}
			return false;
		}

	}



	// PLZ look in #vminstall.php# to add your plugin and module
	function com_install(){

		if(!version_compare(JVERSION,'1.6.0','ge')) {
			$vmInstall = new com_virtuemart_allinoneInstallerScript();
			$vmInstall->vmInstall();
		}
		return true;
	}

	function com_uninstall(){

		return true;
	}

} //if defined
// pure php no tag
