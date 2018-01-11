<?php
/*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*	@author PrestaShop SA <contact@prestashop.com>
*	@copyright	2007-2016 PrestaShop SA
*	@version	Release: $Revision: 11834 $
*	@license		http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*	International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\Module\AutoUpgrade\Upgrader;
use PrestaShop\Module\AutoUpgrade\PrestashopConfiguration;
use PrestaShop\Module\AutoUpgrade\Tools14;
use PrestaShop\Module\AutoUpgrade\UpgradeTools\Database;
use PrestaShop\Module\AutoUpgrade\UpgradeTools\ModuleAdapter;
use PrestaShop\Module\AutoUpgrade\Parameters\FileConfigurationStorage;
use PrestaShop\Module\AutoUpgrade\Parameters\UpgradeConfigurationStorage;
use PrestaShop\Module\AutoUpgrade\Parameters\UpgradeConfiguration;
use PrestaShop\Module\AutoUpgrade\Temp\JsTemplateFormAdapter;

use PrestaShop\Module\AutoUpgrade\ChannelInfo;
use PrestaShop\Module\AutoUpgrade\BackupFinder;
use PrestaShop\Module\AutoUpgrade\State;
use PrestaShop\Module\AutoUpgrade\UpgradePage;
use PrestaShop\Module\AutoUpgrade\UpgradeSelfCheck;
use PrestaShop\Module\AutoUpgrade\Twig\TransFilterExtension;

require __DIR__.'/vendor/autoload.php';

class AdminSelfUpgrade extends ModuleAdminController
{
    public $multishop_context;
    public $multishop_context_group = false;
    public $_html = '';
    // used for translations
    public static $l_cache;
    // retrocompatibility
    public $noTabLink = array();
    public $id = -1;

    public $ajax = false;
    public $nextResponseType = 'json'; // json, xml
    public $next = 'N/A';

    public $upgrader = null;
    public $standalone = true;

    /**
     * set to false if the current step is a loop
     *
     * @var boolean
     */
    public $stepDone = true;
    public $status = true;
    public $warning_exists = false;
    public $error = '0';
    public $next_desc = '';
    public $nextParams = array();
    public $nextQuickInfo = array();
    public $nextErrors = array();
    public $currentParams = array();
    /**
     * @var array theses values will be automatically added in "nextParams"
     * if their properties exists
     */
    public $ajaxParams = array(
        // autoupgrade options
        'install_version',
        'backupName',
        'backupFilesFilename',
        'backupDbFilename',
        'restoreName',
        'restoreFilesFilename',
        'restoreDbFilenames',
        'installedLanguagesIso',
        'modules_addons',
        'warning_exists',
    );

    /**
     * installedLanguagesIso is an array of iso_code of each installed languages
     *
     * @var array
     * @access public
     */
    public $installedLanguagesIso = array();

    /**
     * modules_addons is an array of array(id_addons => name_module).
     *
     * @var array
     * @access public
     */
    public $modules_addons = array();

    public $autoupgradePath = null;
    public $downloadPath = null;
    public $backupPath = null;
    public $latestPath = null;
    public $tmpPath = null;

    /**
     * autoupgradeDir
     *
     * @var string directory relative to admin dir
     */
    public $autoupgradeDir = 'autoupgrade';
    public $latestRootDir = '';
    public $prodRootDir = '';
    public $adminDir = '';

    public $destDownloadFilename = 'prestashop.zip';

    /**
     * configFilename contains all configuration specific to the autoupgrade module
     *
     * @var string
     * @access public
     */
    public $configFilename = 'config.var';
    /**
     * during upgradeFiles process,
     * this files contains the list of queries left to upgrade in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     * @var string
     */
    public $toUpgradeQueriesList = 'queriesToUpgrade.list';
    /**
     * during upgradeFiles process,
     * this files contains the list of files left to upgrade in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     * @var string
     */
    public $toUpgradeFileList = 'filesToUpgrade.list';
    /**
     * during upgradeModules process,
     * this files contains the list of modules left to upgrade in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     * @var string
     */
    public $toUpgradeModuleList = 'modulesToUpgrade.list';
    /**
     * during upgradeFiles process,
     * this files contains the list of files left to upgrade in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     * @var string
     */
    public $diffFileList = 'filesDiff.list';
    /**
     * during backupFiles process,
     * this files contains the list of files left to save in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     * @var string
     */
    public $toBackupFileList = 'filesToBackup.list';
    /**
     * during backupDb process,
     * this files contains the list of tables left to save in a serialized array.
     * (this file is deleted in init() method if you reload the page)
     * @var string
     */
    public $toBackupDbList = 'tablesToBackup.list';
    /**
     * during restoreDb process,
     * this file contains a serialized array of queries which left to execute for restoring database
     * (this file is deleted in init() method if you reload the page)
     * @var string
     */
    public $toRestoreQueryList = 'queryToRestore.list';
    public $toCleanTable = 'tableToClean.list';

    /**
     * during restoreFiles process,
     * this file contains difference between queryToRestore and queries present in a backupFiles archive
     * (this file is deleted in init() method if you reload the page)
     * @var string
     */
    public $toRemoveFileList = 'filesToRemove.list';
    /**
     * during restoreFiles process,
     * contains list of files present in backupFiles archive
     *
     * @var string
     */
    public $fromArchiveFileList = 'filesFromArchive.list';

    /**
     * mailCustomList contains list of mails files which are customized,
     * relative to original files for the current PrestaShop version
     *
     * @var string
     */
    public $mailCustomList = 'mails-custom.list';

    /**
     * tradCustomList contains list of mails files which are customized,
     * relative to original files for the current PrestaShop version
     *
     * @var string
     */
    public $tradCustomList = 'translations-custom.list';
    /**
     * tmp_files contains an array of filename which will be removed
     * at the beginning of the upgrade process
     *
     * @var array
     */
    public $tmp_files = array(
        'toUpgradeFileList',
        'toUpgradeQueriesList',
        'diffFileList',
        'toBackupFileList',
        'toBackupDbList',
        'toRestoreQueryList',
        'toCleanTable',
        'toRemoveFileList',
        'fromArchiveFileList',
        'tradCustomList',
        'mailCustomList',
    );

    public $install_version;
    public $keepImages = null;
    public $updateDefaultTheme = null;
    public $changeToDefaultTheme = null;
    public $keepMails = null;
    public $manualMode = null;
    public $deactivateCustomModule = null;

    public $sampleFileList = array();
    private $restoreIgnoreFiles = array();
    private $restoreIgnoreAbsoluteFiles = array();
    private $backupIgnoreFiles = array();
    private $backupIgnoreAbsoluteFiles = array();
    private $excludeFilesFromUpgrade = array();
    private $excludeAbsoluteFilesFromUpgrade = array();

    public static $classes14 = array('Cache', 'CacheFS', 'CarrierModule', 'Db', 'FrontController', 'Helper','ImportModule',
    'MCached', 'Module', 'ModuleGraph', 'ModuleGraphEngine', 'ModuleGrid', 'ModuleGridEngine',
    'MySQL', 'Order', 'OrderDetail', 'OrderDiscount', 'OrderHistory', 'OrderMessage', 'OrderReturn',
    'OrderReturnState', 'OrderSlip', 'OrderState', 'PDF', 'RangePrice', 'RangeWeight', 'StockMvt',
    'StockMvtReason', 'SubDomain', 'Shop', 'Tax', 'TaxRule', 'TaxRulesGroup', 'WebserviceKey', 'WebserviceRequest', '');

    private $restoreName = null;
    private $backupName = null;
    private $backupFilesFilename = null;
    private $backupDbFilename = null;
    private $restoreFilesFilename = null;
    private $restoreDbFilenames = array();

    public static $loopBackupFiles = 400;
    public static $maxBackupFileSize = 15728640; // 15 Mo
    public static $loopBackupDbTime = 6;
    public static $max_written_allowed = 4194304; // 4096 ko
    public static $loopUpgradeFiles = 600;
    public static $loopRestoreFiles = 400;
    public static $loopRestoreQueryTime = 6;
    public static $loopUpgradeModulesTime = 6;
    public static $loopRemoveSamples = 400;

    /* usage :  key = the step you want to ski
     * value = the next step you want instead
     *	example : public static $skipAction = array();
     *	initial order upgrade:
     *		download, unzip, removeSamples, backupFiles, backupDb, upgradeFiles, upgradeDb, upgradeModules, cleanDatabase, upgradeComplete
     * initial order rollback: rollback, restoreFiles, restoreDb, rollbackComplete
     */
    public static $skipAction = array();

    /**
     * if set to true, will use pclZip library
     * even if ZipArchive is available
     */
    public static $force_pclZip = false;

    protected $_includeContainer = true;

    public $_fieldsUpgradeOptions = array();
    public $_fieldsBackupOptions = array();

    /**
     * @var PrestashopConfiguration
     */
    private $prestashopConfiguration;

    /**
     * @var UpgradeConfiguration
     */
    private $upgradeConfiguration;
    private $upgradeConfFilePath;

    /**
     * @var Database
     */
    private $databaseTools;

    /**
     * @var ModuleAdapter
     */
    private $moduleAdapter;

    /**
     * replace tools encrypt
     *
     * @param mixed $string
     * @return void
     */
    public function encrypt($string)
    {
        return md5(_COOKIE_KEY_.$string);
    }

    public function checkToken()
    {
        // simple checkToken in ajax-mode, to be free of Cookie class (and no Tools::encrypt() too )
        if ($this->ajax && isset($_COOKIE['id_employee'])) {
            return ($_COOKIE['autoupgrade'] == $this->encrypt($_COOKIE['id_employee']));
        } else {
            return parent::checkToken();
        }
    }

    /**
     * create cookies id_employee, id_tab and autoupgrade (token)
     */
    public function createCustomToken()
    {
        // ajax-mode for autoupgrade, we can't use the classic authentication
        // so, we'll create a cookie in admin dir, based on cookie key
        global $cookie;
        $id_employee = $cookie->id_employee;
        if ($cookie->id_lang) {
            $iso_code = $_COOKIE['iso_code'] = Language::getIsoById((int)$cookie->id_lang);
        } else {
            $iso_code = 'en';
        }
        $admin_dir = trim(str_replace($this->prodRootDir, '', $this->adminDir), DIRECTORY_SEPARATOR);
        $cookiePath = __PS_BASE_URI__.$admin_dir;
        setcookie('id_employee', $id_employee, 0, $cookiePath);
        setcookie('id_tab', $this->id, 0, $cookiePath);
        setcookie('iso_code', $iso_code, 0, $cookiePath);
        setcookie('autoupgrade', $this->encrypt($id_employee), 0, $cookiePath);
        return false;
    }

    public function viewAccess($disable = false)
    {
        if ($this->ajax) {
            return true;
        } else {
            // simple access : we'll allow only 46admin
            global $cookie;
            if ($cookie->profile == 1) {
                return true;
            }
        }
        return false;
    }

    public function __construct()
    {
        parent::__construct();
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('magic_quotes_runtime', '0');
        @ini_set('magic_quotes_sybase', '0');

        $this->init();

        $this->db = Db::getInstance();
        $this->bootstrap = true;

        $this->databaseTools = new Database($this->db);
        $this->moduleAdapter = new ModuleAdapter($this->db);
        // Init PrestashopCompliancy class
        $admin_dir = trim(str_replace($this->prodRootDir, '', $this->adminDir), DIRECTORY_SEPARATOR);
        $this->prestashopConfiguration = new PrestashopConfiguration(
            $admin_dir.DIRECTORY_SEPARATOR.$this->autoupgradeDir,
            $this->upgrader->autoupgrade_last_version
        );

        // Performance settings, if your server has a low memory size, lower these values
        $perf_array = array(
            'loopBackupFiles' => array(400, 800, 1600),
            'maxBackupFileSize' => array(15728640, 31457280, 62914560),
            'loopBackupDbTime' => array(6, 12, 25),
            'max_written_allowed' => array(4194304, 8388608, 16777216),
            'loopUpgradeFiles' => array(600, 1200, 2400),
            'loopRestoreFiles' => array(400, 800, 1600),
            'loopRestoreQueryTime' => array(6, 12, 25),
            'loopUpgradeModulesTime' => array(6, 12, 25),
            'loopRemoveSamples' => array(400, 800, 1600)
        );
        switch ($this->upgradeConfiguration->get('PS_AUTOUP_PERFORMANCE')) {
            case 3:
                foreach ($perf_array as $property => $values) {
                    self::$$property = $values[2];
                }
                break;
            case 2:
                foreach ($perf_array as $property => $values) {
                    self::$$property = $values[1];
                }
                break;
            case 1:
            default:
                foreach ($perf_array as $property => $values) {
                    self::$$property = $values[0];
                }
        }

        self::$currentIndex = $_SERVER['SCRIPT_NAME'].(($controller = Tools14::getValue('controller')) ? '?controller='.$controller: '');

        if (defined('_PS_ADMIN_DIR_')) {
            $file_tab = @filemtime($this->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php');
            $file =  @filemtime(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$this->autoupgradeDir.DIRECTORY_SEPARATOR.'ajax-upgradetab.php');

            if ($file_tab < $file) {
                @copy(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$this->autoupgradeDir.DIRECTORY_SEPARATOR.'ajax-upgradetab.php',
                    $this->autoupgradePath.DIRECTORY_SEPARATOR.'ajax-upgradetab.php');
            }
        }

        if (!$this->ajax) {
            Context::getContext()->smarty->assign('display_header_javascript', true);
        }
    }

    /**
     * function to set configuration fields display
     *
     * @return void
     */
    private function _setFields()
    {
        $this->_fieldsBackupOptions = array(
            'PS_AUTOUP_BACKUP' => array(
                'title' => $this->trans('Back up my files and database', array(), 'Modules.Autoupgrade.Admin'), 'cast' => 'intval', 'validation' => 'isBool', 'defaultValue' => '1',
                'type' => 'bool', 'desc' => $this->trans('Automatically back up your database and files in order to restore your shop if needed. This is experimental: you should still perform your own manual backup for safety.', array(), 'Modules.Autoupgrade.Admin'),
            ),
            'PS_AUTOUP_KEEP_IMAGES' => array(
                'title' => $this->trans('Back up my images', array(), 'Modules.Autoupgrade.Admin'), 'cast' => 'intval', 'validation' => 'isBool', 'defaultValue' => '1',
                'type' => 'bool', 'desc' => $this->trans('To save time, you can decide not to back your images up. In any case, always make sure you did back them up manually.', array(), 'Modules.Autoupgrade.Admin'),
            ),
        );
        $this->_fieldsUpgradeOptions = array(
            'PS_AUTOUP_PERFORMANCE' => array(
                'title' => $this->trans('Server performance', array(), 'Modules.Autoupgrade.Admin'), 'cast' => 'intval', 'validation' => 'isInt', 'defaultValue' => '1',
                'type' => 'select', 'desc' => $this->trans('Unless you are using a dedicated server, select "Low".', array(), 'Modules.Autoupgrade.Admin').'<br />'.
                $this->trans('A high value can cause the upgrade to fail if your server is not powerful enough to process the upgrade tasks in a short amount of time.', array(), 'Modules.Autoupgrade.Admin'),
                'choices' => array(1 => $this->trans('Low (recommended)', array(), 'Modules.Autoupgrade.Admin'), 2 => $this->trans('Medium', array(), 'Modules.Autoupgrade.Admin'), 3 => $this->trans('High', array(), 'Modules.Autoupgrade.Admin'))
            ),
            'PS_AUTOUP_CUSTOM_MOD_DESACT' => array(
                'title' => $this->trans('Disable non-native modules', array(), 'Modules.Autoupgrade.Admin'), 'cast' => 'intval', 'validation' => 'isBool',
                'type' => 'bool', 'desc' => $this->trans('As non-native modules can experience some compatibility issues, we recommend to disable them by default.', array(), 'Modules.Autoupgrade.Admin').'<br />'.
                $this->trans('Keeping them enabled might prevent you from loading the "Modules" page properly after the upgrade.', array(), 'Modules.Autoupgrade.Admin'),
            ),
            'PS_AUTOUP_UPDATE_DEFAULT_THEME' => array(
                'title' => $this->trans('Upgrade the default theme', array(), 'Modules.Autoupgrade.Admin'), 'cast' => 'intval', 'validation' => 'isBool', 'defaultValue' => '1',
                'type' => 'bool', 'desc' => $this->trans('If you customized the default PrestaShop theme in its folder (folder name "classic" in 1.7), enabling this option will lose your modifications.', array(), 'Modules.Autoupgrade.Admin').'<br />'
                .$this->trans('If you are using your own theme, enabling this option will simply update the default theme files, and your own theme will be safe.', array(), 'Modules.Autoupgrade.Admin'),
            ),
            'PS_AUTOUP_CHANGE_DEFAULT_THEME' => array(
                'title' => $this->trans('Switch to the default theme', array(), 'Modules.Autoupgrade.Admin'), 'cast' => 'intval', 'validation' => 'isBool', 'defaultValue' => '0',
                'type' => 'bool', 'desc' => $this->trans('This will change your theme: your shop will then use the default theme of the version of PrestaShop you are upgrading to.', array(), 'Modules.Autoupgrade.Admin'),
            ),
            'PS_AUTOUP_KEEP_MAILS' => array(
                'title' => $this->trans('Keep the customized email templates', array(), 'Modules.Autoupgrade.Admin'), 'cast' => 'intval', 'validation' => 'isBool',
                'type' => 'bool', 'desc' => $this->trans('This will not upgrade the default PrestaShop e-mails.', array(), 'Modules.Autoupgrade.Admin').'<br />'
                .$this->trans('If you customized the default PrestaShop e-mail templates, enabling this option will keep your modifications.', array(), 'Modules.Autoupgrade.Admin'),
            ),
        );
    }

    public function cleanTmpFiles()
    {
        foreach ($this->tmp_files as $tmp_file) {
            if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->$tmp_file)) {
                unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->$tmp_file);
            }
        }
    }

    /**
     * init to build informations we need
     *
     * @return void
     */
    public function init()
    {
        if (!$this->ajax) {
            parent::init();
        }
        // For later use, let's set up prodRootDir and adminDir
        // This way it will be easier to upgrade a different path if needed
        $this->prodRootDir = _PS_ROOT_DIR_;
        $this->adminDir = realpath(_PS_ADMIN_DIR_);
        if (!defined('__PS_BASE_URI__')) {
            // _PS_DIRECTORY_ replaces __PS_BASE_URI__ in 1.5
            if (defined('_PS_DIRECTORY_')) {
                define('__PS_BASE_URI__', _PS_DIRECTORY_);
            } else {
                define('__PS_BASE_URI__', realpath(dirname($_SERVER['SCRIPT_NAME'])).'/../../');
            }
        }
        // from $_POST or $_GET
        $this->action = empty($_REQUEST['action'])?null:$_REQUEST['action'];
        $this->currentParams = empty($_REQUEST['params'])?null:$_REQUEST['params'];
        // test writable recursively
        if (!class_exists('ConfigurationTest', false)) {
            require_once(dirname(__FILE__).'/classes/ConfigurationTest.php');
            if (!class_exists('ConfigurationTest', false) and class_exists('ConfigurationTestCore')) {
                eval('class ConfigurationTest extends ConfigurationTestCore{}');
            }
        }
        $this->initPath();
        $this->upgradeConfiguration = UpgradeConfigurationStorage::load($this->upgradeConfFilePath);
        $upgrader = $this->getUpgrader();

        // If you have defined this somewhere, you know what you do
        /* load options from configuration if we're not in ajax mode */
        if (!$this->ajax) {
            $this->createCustomToken();

            $postData = 'version='._PS_VERSION_.'&method=listing&action=native&iso_code=all';
            $xml_local = $this->prodRootDir.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'xml'.DIRECTORY_SEPARATOR.'modules_native_addons.xml';
            $xml = $upgrader->getApiAddons($xml_local, $postData, true);

            if (is_object($xml)) {
                foreach ($xml as $mod) {
                    $this->modules_addons[(string)$mod->id] = (string)$mod->name;
                }
            }

            // installedLanguagesIso is used to merge translations files
            $iso_ids = Language::getIsoIds(false);
            foreach ($iso_ids as $v) {
                $this->installedLanguagesIso[] = $v['iso_code'];
            }

            $rand = dechex(mt_rand(0, min(0xffffffff, mt_getrandmax())));
            $date = date('Ymd-His');
            $this->backupName = 'V'._PS_VERSION_.'_'.$date.'-'.$rand;
            $this->backupFilesFilename = 'auto-backupfiles_'.$this->backupName.'.zip';
            $this->backupDbFilename = 'auto-backupdb_XXXXXX_'.$this->backupName.'.sql';
            // removing temporary files
            $this->cleanTmpFiles();
        } else {
            foreach ($this->ajaxParams as $prop) {
                if (property_exists($this, $prop) && isset($this->currentParams[$prop])) {
                    $this->{$prop} = $this->currentParams[$prop];
                }
            }
        }

        $this->keepImages = $this->upgradeConfiguration->get('PS_AUTOUP_KEEP_IMAGES');
        $this->updateDefaultTheme = $this->upgradeConfiguration->get('PS_AUTOUP_UPDATE_DEFAULT_THEME');
        $this->changeToDefaultTheme = $this->upgradeConfiguration->get('PS_AUTOUP_CHANGE_DEFAULT_THEME');
        $this->keepMails = $this->upgradeConfiguration->get('PS_AUTOUP_KEEP_MAILS');
        $this->manualMode = (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_)? (bool)$this->upgradeConfiguration->get('PS_AUTOUP_MANUAL_MODE') : false;
        $this->deactivateCustomModule = $this->upgradeConfiguration->get('PS_AUTOUP_CUSTOM_MOD_DESACT');

        // during restoration, do not remove :
        $this->restoreIgnoreAbsoluteFiles[] = '/app/config/parameters.php';
        $this->restoreIgnoreAbsoluteFiles[] = '/app/config/parameters.yml';
        $this->restoreIgnoreAbsoluteFiles[] = '/modules/autoupgrade';
        $this->restoreIgnoreAbsoluteFiles[] = '/admin/autoupgrade';
        $this->restoreIgnoreAbsoluteFiles[] = '.';
        $this->restoreIgnoreAbsoluteFiles[] = '..';

        // during backup, do not save
        $this->backupIgnoreAbsoluteFiles[] = '/app/cache';
        $this->backupIgnoreAbsoluteFiles[] = '/cache/smarty/compile';
        $this->backupIgnoreAbsoluteFiles[] = '/cache/smarty/cache';
        $this->backupIgnoreAbsoluteFiles[] = '/cache/tcpdf';
        $this->backupIgnoreAbsoluteFiles[] = '/cache/cachefs';

        // do not care about the two autoupgrade dir we use;
        $this->backupIgnoreAbsoluteFiles[] = '/modules/autoupgrade';
        $this->backupIgnoreAbsoluteFiles[] = '/admin/autoupgrade';

        $this->backupIgnoreFiles[] = '.';
        $this->backupIgnoreFiles[] = '..';
        $this->backupIgnoreFiles[] = '.svn';
        $this->backupIgnoreFiles[] = '.git';
        $this->backupIgnoreFiles[] = $this->autoupgradeDir;

        $this->excludeFilesFromUpgrade[] = '.';
        $this->excludeFilesFromUpgrade[] = '..';
        $this->excludeFilesFromUpgrade[] = '.svn';
        $this->excludeFilesFromUpgrade[] = '.git';

        // do not copy install, neither app/config/parameters.php in case it would be present
        $this->excludeAbsoluteFilesFromUpgrade[] = '/app/config/parameters.php';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/app/config/parameters.yml';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/install';
        $this->excludeAbsoluteFilesFromUpgrade[] = '/install-dev';

        // this will exclude autoupgrade dir from admin, and autoupgrade from modules
        $this->excludeFilesFromUpgrade[] = $this->autoupgradeDir;

        if ($this->keepImages === '0') {
            $this->backupIgnoreAbsoluteFiles[] = '/img';
            $this->restoreIgnoreAbsoluteFiles[] = '/img';
        } else {
            $this->backupIgnoreAbsoluteFiles[] = '/img/tmp';
            $this->restoreIgnoreAbsoluteFiles[] = '/img/tmp';
        }

        if (!$this->updateDefaultTheme) /* If set to false, we need to preserve the default themes */
        {
            $this->excludeAbsoluteFilesFromUpgrade[] = '/themes/classic';
        }
    }

    /**
     * create some required directories if they does not exists
     *
     * Also set nextParams (removeList and filesToUpgrade) if they
     * exists in currentParams
     *
     */
    public function initPath()
    {
        // If not exists in this sessions, "create"
        // session handling : from current to next params
        if (isset($this->currentParams['removeList'])) {
            $this->nextParams['removeList'] = $this->currentParams['removeList'];
        }

        if (isset($this->currentParams['filesToUpgrade'])) {
            $this->nextParams['filesToUpgrade'] = $this->currentParams['filesToUpgrade'];
        }

        if (isset($this->currentParams['modulesToUpgrade'])) {
            $this->nextParams['modulesToUpgrade'] = $this->currentParams['modulesToUpgrade'];
        }

        // set autoupgradePath, to be used in backupFiles and backupDb config values
        $this->autoupgradePath = $this->adminDir.DIRECTORY_SEPARATOR.$this->autoupgradeDir;
        // directory missing
        if (!file_exists($this->autoupgradePath)) {
            if (!mkdir($this->autoupgradePath)) {
                $this->_errors[] = $this->trans('Unable to create directory %s', array($this->autoupgradePath), 'Modules.Autoupgrade.Admin');
            }
        }

        if (!is_writable($this->autoupgradePath)) {
            $this->_errors[] = $this->trans('Unable to write in the directory "%s"', array($this->autoupgradePath), 'Modules.Autoupgrade.Admin');
        }

        $this->downloadPath = $this->autoupgradePath.DIRECTORY_SEPARATOR.'download';
        if (!file_exists($this->downloadPath)) {
            if (!mkdir($this->downloadPath)) {
                $this->_errors[] = $this->trans('Unable to create directory %s', array($this->downloadPath), 'Modules.Autoupgrade.Admin');
            }
        }

        $this->backupPath = $this->autoupgradePath.DIRECTORY_SEPARATOR.'backup';
        $tmp = "order deny,allow\ndeny from all";
        if (!file_exists($this->backupPath)) {
            if (!mkdir($this->backupPath)) {
                $this->_errors[] = $this->trans('Unable to create directory %s', array($this->backupPath), 'Modules.Autoupgrade.Admin');
            }
        }
        if (!file_exists($this->backupPath.DIRECTORY_SEPARATOR.'index.php')) {
            if (!copy(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'index.php', $this->backupPath.DIRECTORY_SEPARATOR.'index.php')) {
                $this->_errors[] = $this->trans('Unable to create file %s', array($this->backupPath.DIRECTORY_SEPARATOR.'index.php'), 'Modules.Autoupgrade.Admin');
            }
        }
        if (!file_exists($this->backupPath.DIRECTORY_SEPARATOR.'.htaccess')) {
            if (!file_put_contents($this->backupPath.DIRECTORY_SEPARATOR.'.htaccess', $tmp)) {
                $this->_errors[] = $this->trans('Unable to create file %s', array($this->backupPath.DIRECTORY_SEPARATOR.'.htaccess'), 'Modules.Autoupgrade.Admin');
            }
        }

        // directory missing
        $this->latestPath = $this->autoupgradePath.DIRECTORY_SEPARATOR.'latest';
        if (!file_exists($this->latestPath)) {
            if (!mkdir($this->latestPath)) {
                $this->_errors[] = $this->trans('Unable to create directory %s', array($this->latestPath), 'Modules.Autoupgrade.Admin');
            }
        }

        $this->tmpPath = $this->autoupgradePath.DIRECTORY_SEPARATOR.'tmp';
        if (!file_exists($this->tmpPath)) {
            if (!mkdir($this->tmpPath)) {
                $this->_errors[] = $this->trans('Unable to create directory %s', array($this->tmpPath), 'Modules.Autoupgrade.Admin');
            }
        }

        $this->latestRootDir = $this->latestPath.DIRECTORY_SEPARATOR;
        $this->upgradeConfFilePath = $this->autoupgradePath.DIRECTORY_SEPARATOR.$this->configFilename;
    }

    /**
     * getFilePath return the path to the zipfile containing prestashop.
     *
     * @return void
     */
    private function getFilePath()
    {
        return $this->downloadPath.DIRECTORY_SEPARATOR.$this->destDownloadFilename;
    }

    public function postProcess()
    {
        $this->_setFields();

        // set default configuration to default channel & dafault configuration for backup and upgrade
        // (can be modified in expert mode)
        $config = $this->upgradeConfiguration->get('channel');
        if ($config === false) {
            $config = array();
            $config['channel'] = Upgrader::DEFAULT_CHANNEL;
            $this->writeConfig($config);
            if (class_exists('Configuration', false)) {
                Configuration::updateValue('PS_UPGRADE_CHANNEL', $config['channel']);
            }

            $this->writeConfig(array(
                'PS_AUTOUP_PERFORMANCE' => '1',
                'PS_AUTOUP_CUSTOM_MOD_DESACT' => '1',
                'PS_AUTOUP_UPDATE_DEFAULT_THEME' => '1',
                'PS_AUTOUP_CHANGE_DEFAULT_THEME' => '0',
                'PS_AUTOUP_KEEP_MAILS' => '0',
                'PS_AUTOUP_BACKUP' => '1',
                'PS_AUTOUP_KEEP_IMAGES' => '0'
                ));
        }

        if (Tools14::isSubmit('putUnderMaintenance')) {
            foreach (Shop::getCompleteListOfShopsID() as $id_shop) {
                Configuration::updateValue('PS_SHOP_ENABLE', 0, false, null, (int)$id_shop);
            }
            Configuration::updateGlobalValue('PS_SHOP_ENABLE', 0);
        }

        if (Tools14::isSubmit('customSubmitAutoUpgrade')) {
            $config_keys = array_keys(array_merge($this->_fieldsUpgradeOptions, $this->_fieldsBackupOptions));
            $config = array();
            foreach ($config_keys as $key) {
                if (isset($_POST[$key])) {
                    $config[$key] = $_POST[$key];
                }
            }
            $res = $this->writeConfig($config);
            if ($res) {
                Tools14::redirectAdmin(self::$currentIndex.'&conf=6&token='.Tools14::getValue('token'));
            }
        }

        if (Tools14::isSubmit('deletebackup')) {
            $res = false;
            $name = Tools14::getValue('name');
            $filelist = scandir($this->backupPath);
            foreach ($filelist as $filename) {
                // the following will match file or dir related to the selected backup
                if (!empty($filename) && $filename[0] != '.' && $filename != 'index.php' && $filename != '.htaccess'
                    && preg_match('#^(auto-backupfiles_|)'.preg_quote($name).'(\.zip|)$#', $filename, $matches)) {
                    if (is_file($this->backupPath.DIRECTORY_SEPARATOR.$filename)) {
                        $res &= unlink($this->backupPath.DIRECTORY_SEPARATOR.$filename);
                    } elseif (!empty($name) && is_dir($this->backupPath.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR)) {
                        $res = self::deleteDirectory($this->backupPath.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR);
                    }
                }
            }
            if ($res) {
                Tools14::redirectAdmin(self::$currentIndex.'&conf=1&token='.Tools14::getValue('token'));
            } else {
                $this->_errors[] = $this->trans('Error when trying to delete backups %s', array($name), 'Modules.Autoupgrade.Admin');
            }
        }
        parent::postProcess();
    }

    /**
     * ends the rollback process
     *
     * @return void
     */
    public function ajaxProcessRollbackComplete()
    {
        $this->next_desc = $this->trans('Restoration process done. Congratulations! You can now reactivate your shop.', array(), 'Modules.Autoupgrade.Admin');
        $this->next = '';
    }

    /**
     * ends the upgrade process
     *
     * @return void
     */
    public function ajaxProcessUpgradeComplete()
    {
        if (!$this->warning_exists) {
            $this->next_desc = $this->trans('Upgrade process done. Congratulations! You can now reactivate your shop.', array(), 'Modules.Autoupgrade.Admin');
        } else {
            $this->next_desc = $this->trans('Upgrade process done, but some warnings have been found.', array(), 'Modules.Autoupgrade.Admin');
        }
        $this->next = '';

        if ($this->upgradeConfiguration->get('channel') != 'archive' && file_exists($this->getFilePath()) && unlink($this->getFilePath())) {
            $this->nextQuickInfo[] = $this->trans('%s removed', array($this->getFilePath()), 'Modules.Autoupgrade.Admin');
        } elseif (is_file($this->getFilePath())) {
            $this->nextQuickInfo[] = '<strong>'.$this->trans('Please remove %s by FTP', array($this->getFilePath()), 'Modules.Autoupgrade.Admin').'</strong>';
        }

        if ($this->upgradeConfiguration->get('channel') != 'directory' && file_exists($this->latestRootDir) && self::deleteDirectory($this->latestRootDir)) {
            $this->nextQuickInfo[] = $this->trans('%s removed', array($this->latestRootDir), 'Modules.Autoupgrade.Admin');
        } elseif (is_dir($this->latestRootDir)) {
            $this->nextQuickInfo[] = '<strong>'.$this->trans('Please remove %s by FTP', array($this->latestRootDir), 'Modules.Autoupgrade.Admin').'</strong>';
        }

        $this->clearMigrationCache();
    }

    // Simplification of _displayForm original function
    protected function _displayForm($name, $fields, $tabname, $size, $icon)
    {
        $confValues = $this->upgradeConfiguration->toArray();
        $required = false;

        $this->_html .= '<div class="bootstrap" id="'.$name.'Block">
            <div class="panel">
                <div class="panel-heading">
                  '.$tabname.'
                </div>
                <div class="form-wrapper">';
        foreach ($fields as $key => $field) {
            if (isset($field['required']) && $field['required']) {
                $required = true;
            }

            if (isset($field['disabled']) && $field['disabled']) {
                $disabled = true;
            } else {
                $disabled = false;
            }


            if (isset($confValues[$key])) {
                $val = $confValues[$key];
            } else {
                $val = isset($field['defaultValue'])?$field['defaultValue']:false;
            }

            if (!in_array($field['type'], array('image', 'radio', 'select', 'container', 'bool', 'container_end')) || isset($field['show'])) {
                $this->_html .= '<div style="clear: both; padding-top:15px;">'.($field['title'] ? '<label >'.$field['title'].'</label>' : '').'<div class="margin-form" style="padding-top:5px;">';
            }

            /* Display the appropriate input type for each field */
            switch ($field['type']) {
                case 'disabled':
                    $this->_html .= $field['disabled'];
                    break;


                case 'bool':
                    $this->_html .= '<div class="form-group">
                        <label class="col-lg-3 control-label">'.$field['title'].'</label>
                            <div class="col-lg-9">
                                <span class="switch prestashop-switch fixed-width-lg">
                                    <input type="radio" name="'.$key.'" id="'.$key.'_on" value="1" '.($val ? ' checked="checked"' : '').(isset($field['js']['on']) ? $field['js']['on'] : '').' />
                                    <label for="'.$key.'_on" class="radioCheck">
                                        <i class="color_success"></i> '.$this->trans('Yes', array(), 'Admin.Global').'
                                    </label>
                                    <input type="radio" name="'.$key.'" id="'.$key.'_off" value="0" '.(!$val ? 'checked="checked"' : '').(isset($field['js']['off']) ? $field['js']['off'] : '').'/>
                                    <label for="'.$key.'_off" class="radioCheck">
                                        <i class="color_danger"></i> '.$this->trans('No', array(), 'Admin.Global').'
                                    </label>
                                    <a class="slide-button btn"></a>
                                </span>
                                <div class="help-block">'.$field['desc'].'</div>
                            </div>
                        </div>';
                    break;

                case 'radio':
                    foreach ($field['choices'] as $cValue => $cKey) {
                        $this->_html .= '<input '.($disabled?'disabled="disabled"':'').' type="radio" name="'.$key.'" id="'.$key.$cValue.'_on" value="'.(int)($cValue).'"'.(($cValue == $val) ? ' checked="checked"' : '').(isset($field['js'][$cValue]) ? ' '.$field['js'][$cValue] : '').' /><label class="t" for="'.$key.$cValue.'_on"> '.$cKey.'</label><br />';
                    }
                    $this->_html .= '<br />';
                    break;

                case 'select':
                    $this->_html .= '<div class="form-group">
                        <label class="col-lg-3 control-label">'.$field['title'].'</label>
                            <div class="col-lg-9">
                                <select name='.$key.'>';
                    foreach ($field['choices'] as $cValue => $cKey) {
                        $this->_html .= '<option value="'.(int)$cValue.'"'.(($cValue == $val) ? ' selected="selected"' : '').'>'.$cKey.'</option>';
                    }
                    $this->_html .= '</select>
                        <div class="help-block">'.$field['desc'].'</div>
                        </div>
                    </div>';
                    break;

                case 'textarea':
                    $this->_html .= '<textarea '.($disabled?'disabled="disabled"':'').' name='.$key.' cols="'.$field['cols'].'" rows="'.$field['rows'].'">'.htmlentities($val, ENT_COMPAT, 'UTF-8').'</textarea>';
                    break;

                case 'container':
                    $this->_html .= '<div id="'.$key.'">';
                    break;

                case 'container_end':
                    $this->_html .= (isset($field['content']) === true ? $field['content'] : '').'</div>';
                    break;

                case 'text':
                default:
                    $this->_html .= '<input '.($disabled?'disabled="disabled"':'').' type="'.$field['type'].'"'.(isset($field['id']) === true ? ' id="'.$field['id'].'"' : '').' size="'.(isset($field['size']) ? (int)($field['size']) : 5).'" name="'.$key.'" value="'.($field['type'] == 'password' ? '' : htmlentities($val, ENT_COMPAT, 'UTF-8')).'" />'.(isset($field['next']) ? '&nbsp;'.strval($field['next']) : '');
            }

            $this->_html .= ((isset($field['required']) && $field['required'] && !in_array($field['type'], array('image', 'radio')))  ? ' <sup>*</sup>' : '');

            if (!in_array($field['type'], array('bool', 'select'))) {
                $this->_html .= (isset($field['desc']) ? '<p style="clear:both">'.((isset($field['thumb']) && $field['thumb'] && $field['thumb']['pos'] == 'after') ? '<img src="'.$field['thumb']['file'].'" alt="'.$field['title'].'" title="'.$field['title'].'" style="float:left;" />' : '').$field['desc'].'</p>' : '');
            }

            if (!in_array($field['type'], array('image', 'radio', 'select', 'container', 'bool', 'container_end')) || isset($field['show'])) {
                $this->_html .= '</div></div>';
            }
        }

        $this->_html .= '</div>
            <div class="panel-footer">
                <button type="submit" class="btn btn-default pull-right" value="'.$this->trans('Save', array(), 'Admin.Actions').'" name="customSubmitAutoUpgrade"><i class="process-icon-save"></i>
                    '.$this->trans('Save', array(), 'Admin.Actions').'</button>
            </div>
        </div></div>';
    }

    /**
     * update module configuration (saved in file $this->configFilename) with $new_config
     *
     * @param array $new_config
     * @return boolean true if success
     */
    public function writeConfig($config)
    {
        if (!file_exists($this->upgradeConfFilePath) && !empty($config['channel'])) {
            $this->upgrader->channel = $config['channel'];
            $this->upgrader->checkPSVersion();
            $this->install_version = $this->upgrader->version_num;
        }

        $this->upgradeConfiguration->merge($config);
        $this->next_desc = $this->trans('Configuration successfully updated.', array(), 'Modules.Autoupgrade.Admin').' <strong>'.$this->trans('This page will now be reloaded and the module will check if a new version is available.', array(), 'Modules.Autoupgrade.Admin').'</strong>';
        return UpgradeConfigurationStorage::save($this->upgradeConfiguration, $this->upgradeConfFilePath);
    }

    /**
     * update configuration after validating the new values
     *
     * @access public
     */
    public function ajaxProcessUpdateConfig()
    {
        $config = array();
        // nothing next
        $this->next = '';
        // update channel
        if (isset($this->currentParams['channel'])) {
            $config['channel'] = $this->currentParams['channel'];
        }
        if (isset($this->currentParams['private_release_link']) && isset($this->currentParams['private_release_md5'])) {
            $config['channel'] = 'private';
            $config['private_release_link'] = $this->currentParams['private_release_link'];
            $config['private_release_md5'] = $this->currentParams['private_release_md5'];
            $config['private_allow_major'] = $this->currentParams['private_allow_major'];
        }
        // if (!empty($this->currentParams['archive_name']) && !empty($this->currentParams['archive_num']))
        if (!empty($this->currentParams['archive_prestashop'])) {
            $file = $this->currentParams['archive_prestashop'];
            if (!file_exists($this->downloadPath.DIRECTORY_SEPARATOR.$file)) {
                $this->error = 1;
                $this->next_desc = $this->trans('File %s does not exist. Unable to select that channel.', array($file), 'Modules.Autoupgrade.Admin');
                return false;
            }
            if (empty($this->currentParams['archive_num'])) {
                $this->error = 1;
                $this->next_desc = $this->trans('Version number is missing. Unable to select that channel.', array(), 'Modules.Autoupgrade.Admin');
                return false;
            }
            $config['channel'] = 'archive';
            $config['archive.filename'] = $this->currentParams['archive_prestashop'];
            $config['archive.version_num'] = $this->currentParams['archive_num'];
            // $config['archive_name'] = $this->currentParams['archive_name'];
            $this->next_desc = $this->trans('Upgrade process will use archive.', array(), 'Modules.Autoupgrade.Admin');
        }
        if (isset($this->currentParams['directory_num'])) {
            $config['channel'] = 'directory';
            if (empty($this->currentParams['directory_num']) || strpos($this->currentParams['directory_num'], '.') === false) {
                $this->error = 1;
                $this->next_desc = $this->trans('Version number is missing. Unable to select that channel.', array(), 'Modules.Autoupgrade.Admin');
                return false;
            }

            $config['directory.version_num'] = $this->currentParams['directory_num'];
        }
        if (isset($this->currentParams['skip_backup'])) {
            $config['skip_backup'] = $this->currentParams['skip_backup'];
        }

        if (!$this->writeConfig($config)) {
            $this->error = 1;
            $this->next_desc = $this->trans('Error on saving configuration', array(), 'Modules.Autoupgrade.Admin');
        }
    }

    /**
     * display informations related to the selected channel : link/changelog for remote channel,
     * or configuration values for special channels
     *
     * @access public
     */
    public function ajaxProcessGetChannelInfo()
    {
        // do nothing after this request (see javascript function doAjaxRequest )
        $this->next = '';

        $channel = $this->currentParams['channel'];
        $upgrade_info = (new ChannelInfo($this->getUpgrader(), $this->upgradeConfiguration, $channel))->getInfo();
        $this->nextParams['result']['available'] =  $upgrade_info['available'];

        $this->nextParams['result']['div'] = $this->divChannelInfos($upgrade_info);
    }

    /**
     * get the list of all modified and deleted files between current version
     * and target version (according to channel configuration)
     *
     * @access public
     */
    public function ajaxProcessCompareReleases()
    {
        // do nothing after this request (see javascript function doAjaxRequest )
        $this->next = '';
        $channel = $this->upgradeConfiguration->get('channel');
        $this->upgrader = new Upgrader();
        switch ($channel) {
            case 'archive':
                $version = $this->upgradeConfiguration->get('archive.version_num');
                break;
            case 'directory':
                $version = $this->upgradeConfiguration->get('directory.version_num');
                break;
            default:
                preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
                $this->upgrader->branch = $matches[1];
                $this->upgrader->channel = $channel;
                if ($this->upgradeConfiguration->get('channel') == 'private' && !$this->upgradeConfiguration->get('private_allow_major')) {
                    $this->upgrader->checkPSVersion(false, array('private', 'minor'));
                } else {
                    $this->upgrader->checkPSVersion(false, array('minor'));
                }
                $version = $this->upgrader->version_num;
        }

        $diffFileList = $this->upgrader->getDiffFilesList(_PS_VERSION_, $version);
        if (!is_array($diffFileList)) {
            $this->nextParams['status'] = 'error';
            $this->nextParams['msg'] = sprintf('Unable to generate diff file list between %1$s and %2$s.', _PS_VERSION_, $version);
        } else {
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->diffFileList, base64_encode(serialize($diffFileList)));
            if (count($diffFileList) > 0) {
                $this->nextParams['msg'] = $this->trans(
                    '%modifiedfiles% files will be modified, %deletedfiles% files will be deleted (if they are found).',
                    array(
                        '%modifiedfiles%' => count($diffFileList['modified']),
                        '%deletedfiles%' => count($diffFileList['deleted']),
                    ),
                    'Modules.Autoupgrade.Admin');
            } else {
                $this->nextParams['msg'] = $this->trans('No diff files found.', array(), 'Modules.Autoupgrade.Admin');
            }
            $this->nextParams['result'] = $diffFileList;
        }
    }

    /**
     * list the files modified in the current installation regards to the original version
     *
     * @access public
     */
    public function ajaxProcessCheckFilesVersion()
    {
        // do nothing after this request (see javascript function doAjaxRequest )
        $this->next = '';
        $this->upgrader = new Upgrader();

        $changedFileList = $this->upgrader->getChangedFilesList();

        if ($this->upgrader->isAuthenticPrestashopVersion() === true
            && !is_array($changedFileList)) {
            $this->nextParams['status'] = 'error';
            $this->nextParams['msg'] = $this->trans('Unable to check files for the installed version of PrestaShop.', array(), 'Modules.Autoupgrade.Admin');
            $testOrigCore = false;
        } else {
            if ($this->upgrader->isAuthenticPrestashopVersion() === true) {
                $this->nextParams['status'] = 'ok';
                $testOrigCore = true;
            } else {
                $testOrigCore = false;
                $this->nextParams['status'] = 'warn';
            }

            if (!isset($changedFileList['core'])) {
                $changedFileList['core'] = array();
            }

            if (!isset($changedFileList['translation'])) {
                $changedFileList['translation'] = array();
            }
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->tradCustomList, base64_encode(serialize($changedFileList['translation'])));

            if (!isset($changedFileList['mail'])) {
                $changedFileList['mail'] = array();
            }
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->mailCustomList, base64_encode(serialize($changedFileList['mail'])));


            if ($changedFileList === false) {
                $changedFileList = array();
                $this->nextParams['msg'] = $this->trans('Unable to check files', array(), 'Modules.Autoupgrade.Admin');
                $this->nextParams['status'] = 'error';
            } else {
                $this->nextParams['msg'] = ($testOrigCore ? $this->trans('Core files are ok', array(), 'Modules.Autoupgrade.Admin') : $this->trans(
                    '%modificationscount% file modifications have been detected, including %coremodifications% from core and native modules:',
                    array(
                        '%modificationscount%' => count(array_merge($changedFileList['core'], $changedFileList['mail'], $changedFileList['translation'])),
                        '%coremodifications%' => count($changedFileList['core']),
                    ),
                    'Modules.Autoupgrade.Admin')
                );
            }
            $this->nextParams['result'] = $changedFileList;
        }
    }

    /**
     * very first step of the upgrade process. The only thing done is the selection
     * of the next step
     *
     * @access public
     * @return void
     */
    public function ajaxProcessUpgradeNow()
    {
        $this->next_desc = $this->trans('Starting upgrade...', array(), 'Modules.Autoupgrade.Admin');

        $channel = $this->upgradeConfiguration->get('channel');
        $this->next = 'download';
        if (!is_object($this->upgrader)) {
            $this->upgrader = new Upgrader();
        }
        preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
        $this->upgrader->branch = $matches[1];
        $this->upgrader->channel = $channel;
        if ($this->upgradeConfiguration->get('channel') == 'private' && !$this->upgradeConfiguration->get('private_allow_major')) {
            $this->upgrader->checkPSVersion(false, array('private', 'minor'));
        } else {
            $this->upgrader->checkPSVersion(false, array('minor'));
        }

        switch ($channel) {
            case 'directory':
                // if channel directory is chosen, we assume it's "ready for use" (samples already removed for example)
                $this->next = 'removeSamples';
                $this->nextQuickInfo[] = $this->trans('Skip downloading and unzipping steps, upgrade process will now remove sample data.', array(), 'Modules.Autoupgrade.Admin');
                $this->next_desc = $this->trans('Shop deactivated. Removing sample files...', array(), 'Modules.Autoupgrade.Admin');
                break;
            case 'archive':
                $this->next = 'unzip';
                $this->nextQuickInfo[] = $this->trans('Skip downloading step, upgrade process will now unzip the local archive.', array(), 'Modules.Autoupgrade.Admin');
                $this->next_desc = $this->trans('Shop deactivated. Extracting files...', array(), 'Modules.Autoupgrade.Admin');
                break;
            default:
                $this->next = 'download';
                $this->next_desc = $this->trans('Shop deactivated. Now downloading... (this can take a while)', array(), 'Modules.Autoupgrade.Admin');
                if ($this->upgrader->channel == 'private') {
                    $this->upgrader->link = $this->upgradeConfiguration->get('private_release_link');
                    $this->upgrader->md5 = $this->upgradeConfiguration->get('private_release_md5');
                }
                $this->nextQuickInfo[] = $this->trans('Downloaded archive will come from %s', array($this->upgrader->link), 'Modules.Autoupgrade.Admin');
                $this->nextQuickInfo[] = $this->trans('MD5 hash will be checked against %s', array($this->upgrader->md5), 'Modules.Autoupgrade.Admin');
        }
    }

    /**
     * extract chosen version into $this->latestPath directory
     *
     * @return void
     */
    public function ajaxProcessUnzip()
    {
        $filepath = $this->getFilePath();
        $destExtract = $this->latestPath;

        if (file_exists($destExtract)) {
            self::deleteDirectory($destExtract, false);
            $this->nextQuickInfo[] = $this->trans('"/latest" directory has been emptied', array(), 'Modules.Autoupgrade.Admin');
        }
        $relative_extract_path = str_replace(_PS_ROOT_DIR_, '', $destExtract);
        $report = '';
        if (ConfigurationTest::test_dir($relative_extract_path, false, $report)) {
            if ($this->ZipExtract($filepath, $destExtract)) {

                // new system release archive
                $newZip = $destExtract.DIRECTORY_SEPARATOR.'prestashop.zip';
                if (is_file($newZip)) {
                    @unlink($destExtract.DIRECTORY_SEPARATOR.'/index.php');
                    @unlink($destExtract.DIRECTORY_SEPARATOR.'/Install_PrestaShop.html');

                    if ($this->ZipExtract($newZip, $destExtract)) {
                        // Unsetting to force listing
                        unset($this->nextParams['removeList']);
                        $this->next = 'removeSamples';
                        $this->next_desc = $this->trans('File extraction complete. Removing sample files...', array(), 'Modules.Autoupgrade.Admin');

                        @unlink($newZip);

                        return true;
                    } else {
                        $this->next = 'error';
                        $this->next_desc = $this->trans(
                            'Unable to extract %filepath% file into %destination% folder...',
                            array(
                                '%filepath%' => $filepath,
                                '%destination%' => $destExtract,
                            ),
                            'Modules.Autoupgrade.Admin'
                        );
                        return false;
                    }
                } else {
                    $this->next = 'error';
                    $this->next_desc = $this->trans('This is not a valid archive for version %s.', array(INSTALL_VERSION), 'Modules.Autoupgrade.Admin');
                    return false;
                }
            } else {
                $this->next = 'error';
                $this->next_desc = $this->trans(
                    'Unable to extract %filepath% file into %destination% folder...',
                    array(
                        '%filepath%' => $filepath,
                        '%destination%' => $destExtract,
                    ),
                    'Modules.Autoupgrade.Admin'
                );
                return false;
            }
        } else {
            $this->next_desc = $this->trans('Extraction directory is not writable.', array(), 'Modules.Autoupgrade.Admin');
            $this->nextQuickInfo[] = $this->trans('Extraction directory is not writable.', array(), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('Extraction directory %s is not writable.', array($destExtract), 'Modules.Autoupgrade.Admin');
            $this->next = 'error';
            return false;
        }
    }


    /**
     * _listSampleFiles will make a recursive call to scandir() function
     * and list all file which match to the $fileext suffixe (this can be an extension or whole filename)
     *
     * @param string $dir directory to look in
     * @param string $fileext suffixe filename
     * @return void
     */
    private function _listSampleFiles($dir, $fileext = '.jpg')
    {
        $res = false;
        $dir = rtrim($dir, '/').DIRECTORY_SEPARATOR;
        $toDel = false;
        if (is_dir($dir) && is_readable($dir)) {
            $toDel = scandir($dir);
        }
        // copied (and kind of) adapted from AdminImages.php
        if (is_array($toDel)) {
            foreach ($toDel as $file) {
                if ($file[0] != '.') {
                    if (preg_match('#'.preg_quote($fileext, '#').'$#i', $file)) {
                        $this->sampleFileList[] = $dir.$file;
                    } elseif (is_dir($dir.$file)) {
                        $res &= $this->_listSampleFiles($dir.$file, $fileext);
                    }
                }
            }
        }
        return $res;
    }

    public function _listFilesInDir($dir, $way = 'backup', $list_directories = false)
    {
        $list = array();
        $dir = rtrim($dir, '/').DIRECTORY_SEPARATOR;
        $allFiles = false;
        if (is_dir($dir) && is_readable($dir)) {
            $allFiles = scandir($dir);
        }
        if (is_array($allFiles)) {
            foreach ($allFiles as $file) {
                if ($file[0] != '.') {
                    $fullPath = $dir.$file;
                    // skip broken symbolic links
                    if (is_link($fullPath) && !is_readable($fullPath)) {
                        continue;
                    }
                    if (!$this->_skipFile($file, $fullPath, $way)) {
                        if (is_dir($fullPath)) {
                            $list = array_merge($list, $this->_listFilesInDir($fullPath, $way, $list_directories));
                            if ($list_directories) {
                                $list[] = $fullPath;
                            }
                        } else {
                            $list[] = $fullPath;
                        }
                    }
                }
            }
        }
        return $list;
    }


    /**
     * this function list all files that will be remove to retrieve the filesystem states before the upgrade
     *
     * @access public
     * @return void
     */
    public function _listFilesToRemove()
    {
        $prev_version = preg_match('#auto-backupfiles_V([0-9.]*)_#', $this->restoreFilesFilename, $matches);
        if ($prev_version) {
            $prev_version = $matches[1];
        }

        if (!$this->upgrader) {
            $this->upgrader = new Upgrader();
        }

        $toRemove = false;
        // note : getDiffFilesList does not include files moved by upgrade scripts,
        // so this method can't be trusted to fully restore directory
        // $toRemove = $this->upgrader->getDiffFilesList(_PS_VERSION_, $prev_version, false);
        // if we can't find the diff file list corresponding to _PS_VERSION_ and prev_version,
        // let's assume to remove every files
        if (!$toRemove) {
            $toRemove = $this->_listFilesInDir($this->prodRootDir, 'restore', true);
        }

        $admin_dir = str_replace($this->prodRootDir, '', $this->adminDir);
        // if a file in "ToRemove" has been skipped during backup,
        // just keep it
        foreach ($toRemove as $key => $file) {
            $filename = substr($file, strrpos($file, '/')+1);
            $toRemove[$key] = preg_replace('#^/admin#', $admin_dir, $file);
            // this is a really sensitive part, so we add an extra checks: preserve everything that contains "autoupgrade"
            if ($this->_skipFile($filename, $file, 'backup') || strpos($file, $this->autoupgradeDir)) {
                unset($toRemove[$key]);
            }
        }
        return $toRemove;
    }

    /**
     * list files to upgrade and return it as array
     *
     * @param string $dir
     * @return number of files found
     */
    public function _listFilesToUpgrade($dir)
    {
        static $list = array();
        if (!is_dir($dir)) {
            $this->nextQuickInfo[] = $this->trans('[ERROR] %s does not exist or is not a directory.', array($dir), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('[ERROR] %s does not exist or is not a directory.', array($dir), 'Modules.Autoupgrade.Admin');
            $this->next_desc = $this->trans('Nothing has been extracted. It seems the unzipping step has been skipped.', array(), 'Modules.Autoupgrade.Admin');
            $this->next = 'error';
            return false;
        }

        $allFiles = scandir($dir);
        foreach ($allFiles as $file) {
            $fullPath = $dir.DIRECTORY_SEPARATOR.$file;

            if (!$this->_skipFile($file, $fullPath, "upgrade")) {
                $list[] = str_replace($this->latestRootDir, '', $fullPath);
                // if is_dir, we will create it :)
                if (is_dir($fullPath)) {
                    if (strpos($dir.DIRECTORY_SEPARATOR.$file, 'install') === false) {
                        $this->_listFilesToUpgrade($fullPath);
                    }
                }
            }
        }
        return $list;
    }


    public function ajaxProcessUpgradeFiles()
    {
        $this->nextParams = $this->currentParams;

        $admin_dir = str_replace($this->prodRootDir.DIRECTORY_SEPARATOR, '', $this->adminDir);
        if (file_exists($this->latestRootDir.DIRECTORY_SEPARATOR.'admin')) {
            rename($this->latestRootDir.DIRECTORY_SEPARATOR.'admin', $this->latestRootDir.DIRECTORY_SEPARATOR.$admin_dir);
        } elseif (file_exists($this->latestRootDir.DIRECTORY_SEPARATOR.'admin-dev')) {
            rename($this->latestRootDir.DIRECTORY_SEPARATOR.'admin-dev', $this->latestRootDir.DIRECTORY_SEPARATOR.$admin_dir);
        }
        if (file_exists($this->latestRootDir.DIRECTORY_SEPARATOR.'install-dev')) {
            rename($this->latestRootDir.DIRECTORY_SEPARATOR.'install-dev', $this->latestRootDir.DIRECTORY_SEPARATOR.'install');
        }

        if (!isset($this->nextParams['filesToUpgrade'])) {
            // list saved in $this->toUpgradeFileList
            // get files differences (previously generated)
            $admin_dir = trim(str_replace($this->prodRootDir, '', $this->adminDir), DIRECTORY_SEPARATOR);
            $filepath_list_diff = $this->autoupgradePath.DIRECTORY_SEPARATOR.$this->diffFileList;
            if (file_exists($filepath_list_diff)) {
                $list_files_diff = unserialize(base64_decode(file_get_contents($filepath_list_diff)));
                // only keep list of files to delete. The modified files will be listed with _listFilesToUpgrade
                $list_files_diff = $list_files_diff['deleted'];
                foreach ($list_files_diff as $k => $path) {
                    if (preg_match("#autoupgrade#", $path)) {
                        unset($list_files_diff[$k]);
                    } else {
                        $list_files_diff[$k] = str_replace('/'.'admin', '/'.$admin_dir, $path);
                    }
                } // do not replace by DIRECTORY_SEPARATOR
            } else {
                $list_files_diff = array();
            }

            if (!($list_files_to_upgrade = $this->_listFilesToUpgrade($this->latestRootDir))) {
                return false;
            }

            // also add files to remove
            $list_files_to_upgrade = array_merge($list_files_diff, $list_files_to_upgrade);

            $filesToMoveToTheBeginning = array(
                DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php',
                DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'ClassLoader.php',
                DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'autoload_classmap.php',
                DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'autoload_files.php',
                DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'autoload_namespaces.php',
                DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'autoload_psr4.php',
                DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'autoload_real.php',
                DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'autoload_static.php',
                DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'include_paths.php',
            );

            foreach ($filesToMoveToTheBeginning as $file) {
                if ($key = array_search($file, $list_files_to_upgrade)) {
                    unset($list_files_to_upgrade[$key]);
                    $list_files_to_upgrade = array_merge(array($file), $list_files_to_upgrade);
                }
            }

            // save in a serialized array in $this->toUpgradeFileList
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toUpgradeFileList, base64_encode(serialize($list_files_to_upgrade)));
            $this->nextParams['filesToUpgrade'] = $this->toUpgradeFileList;
            $total_files_to_upgrade = count($list_files_to_upgrade);

            if ($total_files_to_upgrade == 0) {
                $this->nextQuickInfo[] = $this->trans('[ERROR] Unable to find files to upgrade.', array(), 'Modules.Autoupgrade.Admin');
                $this->nextErrors[] = $this->trans('[ERROR] Unable to find files to upgrade.', array(), 'Modules.Autoupgrade.Admin');
                $this->next_desc = $this->trans('Unable to list files to upgrade', array(), 'Modules.Autoupgrade.Admin');
                $this->next = 'error';
                return false;
            }
            $this->nextQuickInfo[] = $this->trans('%s files will be upgraded.', array($total_files_to_upgrade), 'Modules.Autoupgrade.Admin');

            $this->next_desc = $this->trans('%s files will be upgraded.', array($total_files_to_upgrade), 'Modules.Autoupgrade.Admin');
            $this->next = 'upgradeFiles';
            $this->stepDone = false;
            return true;
        }

        // later we could choose between _PS_ROOT_DIR_ or _PS_TEST_DIR_
        $this->destUpgradePath = $this->prodRootDir;

        $this->next = 'upgradeFiles';
        $filesToUpgrade = @unserialize(base64_decode(file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->nextParams['filesToUpgrade'])));
        if (!is_array($filesToUpgrade)) {
            $this->next = 'error';
            $this->next_desc = $this->trans('filesToUpgrade is not an array', array(), 'Modules.Autoupgrade.Admin');
            $this->nextQuickInfo[] = $this->trans('filesToUpgrade is not an array', array(), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('filesToUpgrade is not an array', array(), 'Modules.Autoupgrade.Admin');
            return false;
        }

        // @TODO : does not upgrade files in modules, translations if they have not a correct md5 (or crc32, or whatever) from previous version
        for ($i = 0; $i < self::$loopUpgradeFiles; $i++) {
            if (count($filesToUpgrade) <= 0) {
                $this->next = 'upgradeDb';
                if (file_exists(($this->nextParams['filesToUpgrade']))) {
                    unlink($this->nextParams['filesToUpgrade']);
                }
                $this->next_desc = $this->trans('All files upgraded. Now upgrading database...', array(), 'Modules.Autoupgrade.Admin');
                $this->nextResponseType = 'json';
                $this->stepDone = true;
                break;
            }

            $file = array_shift($filesToUpgrade);
            if (!$this->upgradeThisFile($file)) {
                // put the file back to the begin of the list
                $totalFiles = array_unshift($filesToUpgrade, $file);
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->trans('Error when trying to upgrade file %s.', array($file), 'Modules.Autoupgrade.Admin');
                $this->nextErrors[] = $this->trans('Error when trying to upgrade file %s.', array($file), 'Modules.Autoupgrade.Admin');
                break;
            }
        }
        file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->nextParams['filesToUpgrade'], base64_encode(serialize($filesToUpgrade)));
        if (count($filesToUpgrade) > 0) {
            if (count($filesToUpgrade)) {
                $this->next_desc = $this->trans('%s files left to upgrade.', array(count($filesToUpgrade)), 'Modules.Autoupgrade.Admin');
                $this->nextQuickInfo[] = $this->trans('%s files left to upgrade.', array((isset($file)?$file:''), count($filesToUpgrade)), 'Modules.Autoupgrade.Admin');
                $this->stepDone = false;
                @unlink(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR. 'app'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'dev'.DIRECTORY_SEPARATOR.'class_index.php');
                @unlink(_PS_ROOT_DIR_.DIRECTORY_SEPARATOR. 'app'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'prod'.DIRECTORY_SEPARATOR.'class_index.php');
            }
        }
        return true;
    }

    private function createCacheFsDirectories($level_depth, $directory = false)
    {
        if (!$directory) {
            if (!defined('_PS_CACHEFS_DIRECTORY_')) {
                define('_PS_CACHEFS_DIRECTORY_', $this->prodRootDir.'/cache/cachefs/');
            }
            $directory = _PS_CACHEFS_DIRECTORY_;
        }
        $chars = '0123456789abcdef';
        for ($i = 0; $i < strlen($chars); $i++) {
            $new_dir = $directory.$chars[$i].'/';
            if (mkdir($new_dir, 0775) && chmod($new_dir, 0775) && $level_depth - 1 > 0) {
                self::createCacheFsDirectories($level_depth - 1, $new_dir);
            }
        }
    }

    /**
     * list modules to upgrade and save them in a serialized array in $this->toUpgradeModuleList
     *
     * @param string $dir
     * @return number of files found
     */
    public function _listModulesToUpgrade()
    {
        static $list = array();

        $dir = $this->prodRootDir.DIRECTORY_SEPARATOR.'modules';

        if (!is_dir($dir)) {
            $this->nextQuickInfo[] = $this->trans('[ERROR] %s does not exist or is not a directory.', array($dir), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('[ERROR] %s does not exist or is not a directory.', array($dir), 'Modules.Autoupgrade.Admin');
            $this->next_desc = $this->trans('Nothing has been extracted. It seems the unzip step has been skipped.', array(), 'Modules.Autoupgrade.Admin');
            $this->next = 'error';
            return false;
        }

        $allModules = scandir($dir);
        foreach ($allModules as $module_name) {
            if (is_file($dir.DIRECTORY_SEPARATOR.$module_name)) {
                continue;
            } elseif (is_dir($dir.DIRECTORY_SEPARATOR.$module_name.DIRECTORY_SEPARATOR)
                && is_file($dir.DIRECTORY_SEPARATOR.$module_name.DIRECTORY_SEPARATOR.$module_name.'.php')
            ) {
                if (is_array($this->modules_addons)) {
                    $id_addons = array_search($module_name, $this->modules_addons);
                }
                if (isset($id_addons) && $id_addons) {
                    if ($module_name != $this->autoupgradeDir) {
                        $list[] = array('id' => $id_addons, 'name' => $module_name);
                    }
                }
            }
        }
        file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toUpgradeModuleList, base64_encode(serialize($list)));
        $this->nextParams['modulesToUpgrade'] = $this->toUpgradeModuleList;
        return count($list);
    }

    /**
     * upgrade all partners modules according to the installed prestashop version
     *
     * @access public
     * @return void
     */
    public function ajaxProcessUpgradeModules()
    {
        $start_time = time();
        if (!isset($this->nextParams['modulesToUpgrade'])) {
            // list saved in $this->toUpgradeFileList
            $total_modules_to_upgrade = $this->_listModulesToUpgrade();
            if ($total_modules_to_upgrade) {
                $this->nextQuickInfo[] = $this->trans('%s modules will be upgraded.', array($total_modules_to_upgrade), 'Modules.Autoupgrade.Admin');
                $this->next_desc = $this->trans('%s modules will be upgraded.', array($total_modules_to_upgrade), 'Modules.Autoupgrade.Admin');
            }
            $this->stepDone = false;
            $this->next = 'upgradeModules';
            return true;
        }

        $this->next = 'upgradeModules';
        if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->nextParams['modulesToUpgrade'])) {
            $listModules = @unserialize(base64_decode(file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->nextParams['modulesToUpgrade'])));
        } else {
            $listModules = array();
        }

        if (!is_array($listModules)) {
            $this->next = 'upgradeComplete';
            $this->warning_exists = true;
            $this->next_desc = $this->trans('upgradeModule step has not ended correctly.', array(), 'Modules.Autoupgrade.Admin');
            $this->nextQuickInfo[] = $this->trans('listModules is not an array. No module has been updated.', array(), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('listModules is not an array. No module has been updated.', array(), 'Modules.Autoupgrade.Admin');
            return true;
        }

        $time_elapsed = time() - $start_time;
        // module list
        if (count($listModules) > 0) {
            do {
                $module_info = array_shift($listModules);

                $this->upgradeThisModule($module_info['id'], $module_info['name']);
                $time_elapsed = time() - $start_time;
            } while (($time_elapsed < self::$loopUpgradeModulesTime) && count($listModules) > 0);

            $modules_left = count($listModules);
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toUpgradeModuleList, base64_encode(serialize($listModules)));
            unset($listModules);

            $this->next = 'upgradeModules';
            if ($modules_left) {
                $this->next_desc = $this->trans('%s modules left to upgrade.', array($modules_left), 'Modules.Autoupgrade.Admin');
            }
            $this->stepDone = false;
        } else {
            $modules_to_delete['backwardcompatibility'] = 'Backward Compatibility';
            $modules_to_delete['dibs'] = 'Dibs';
            $modules_to_delete['cloudcache'] = 'Cloudcache';
            $modules_to_delete['mobile_theme'] = 'The 1.4 mobile_theme';
            $modules_to_delete['trustedshops'] = 'Trustedshops';
            $modules_to_delete['dejala'] = 'Dejala';
            $modules_to_delete['stripejs'] = 'Stripejs';
            $modules_to_delete['blockvariouslinks'] = 'Block Various Links';

            foreach ($modules_to_delete as $key => $module) {
                $this->db->execute('DELETE ms.*, hm.*
                FROM `'._DB_PREFIX_.'module_shop` ms
                INNER JOIN `'._DB_PREFIX_.'hook_module` hm USING (`id_module`)
                INNER JOIN `'._DB_PREFIX_.'module` m USING (`id_module`)
                WHERE m.`name` LIKE \''.pSQL($key).'\'');
                $this->db->execute('UPDATE `'._DB_PREFIX_.'module` SET `active` = 0 WHERE `name` LIKE \''.pSQL($key).'\'');

                $path = $this->prodRootDir.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.$key.DIRECTORY_SEPARATOR;
                if (file_exists($path.$key.'.php')) {
                    if (self::deleteDirectory($path)) {
                        $this->nextQuickInfo[] = $this->trans(
                            'The %modulename% module is not compatible with version %version%, it will be removed from your FTP.',
                            array(
                                '%modulename%' => $module,
                                '%version%' => $this->install_version,
                            ),
                            'Modules.Autoupgrade.Admin'
                        );
                    } else {
                        $this->nextErrors[] = $this->trans(
                            'The %modulename% module is not compatible with version %version%, please remove it from your FTP.',
                            array(
                                '%modulename%' => $module,
                                '%version%' => $this->install_version,
                            ),
                            'Modules.Autoupgrade.Admin'
                        );
                    }
                }
            }

            $this->stepDone = true;
            $this->status = 'ok';
            $this->next = 'cleanDatabase';
            $this->next_desc = $this->trans('Addons modules files have been upgraded.', array(), 'Modules.Autoupgrade.Admin');
            $this->nextQuickInfo[] = $this->trans('Addons modules files have been upgraded.', array(), 'Modules.Autoupgrade.Admin');
            if ($this->manualMode) {
                $this->writeConfig(array('PS_AUTOUP_MANUAL_MODE' => '0'));
            }
            return true;
        }
        return true;
    }

    /**
     * upgrade module $name (identified by $id_module on addons server)
     *
     * @param mixed $id_module
     * @param mixed $name
     * @access public
     * @return void
     */
    public function upgradeThisModule($id_module, $name)
    {
        $zip_fullpath = $this->tmpPath.DIRECTORY_SEPARATOR.$name.'.zip';

        $dest_extract = $this->prodRootDir.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR;

        $addons_url = 'api.addons.prestashop.com';
        $protocolsList = array('https://' => 443, 'http://' => 80);
        if (!extension_loaded('openssl')) {
            unset($protocolsList['https://']);
        } else {
            unset($protocolsList['http://']);
        }

        $postData = 'version='.$this->install_version.'&method=module&id_module='.(int)$id_module;

        // Make the request
        $opts = array(
            'http'=>array(
                'method'=> 'POST',
                'content' => $postData,
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'timeout' => 10,
            )
        );
        $context = stream_context_create($opts);
        foreach ($protocolsList as $protocol => $port) {
            // file_get_contents can return false if https is not supported (or warning)
            $content = Tools14::file_get_contents($protocol.$addons_url, false, $context);
            if ($content == false || substr($content, 5) == '<?xml') {
                continue;
            }
            if ($content !== null) {
                if ((bool)file_put_contents($zip_fullpath, $content)) {
                    if (filesize($zip_fullpath) <= 300) {
                        unlink($zip_fullpath);
                    }
                    // unzip in modules/[mod name] old files will be conserved
                    elseif ($this->ZipExtract($zip_fullpath, $dest_extract)) {
                        $this->nextQuickInfo[] = $this->trans('The files of module %s have been upgraded.', array($name), 'Modules.Autoupgrade.Admin');
			            if (file_exists($zip_fullpath)) {
                            unlink($zip_fullpath);
                        }
                    } else {
                        $this->nextQuickInfo[] = '<strong>'.$this->trans('[WARNING] Error when trying to upgrade module %s.', array($name), 'Modules.Autoupgrade.Admin').'</strong>';
                        $this->warning_exists = 1;
                    }
                } else {
                    $this->nextQuickInfo[] = '<strong>'.$this->trans('[ERROR] Unable to write module %s\'s zip file in temporary directory.', array($name), 'Modules.Autoupgrade.Admin').'</strong>';
                    $this->nextErrors[] = '<strong>'.$this->trans('[ERROR] Unable to write module %s\'s zip file in temporary directory.', array($name), 'Modules.Autoupgrade.Admin').'</strong>';
                    $this->warning_exists = 1;
                }
            } else {
                $this->nextQuickInfo[] = '<strong>'.$this->trans('[ERROR] No response from Addons server.', array(), 'Modules.Autoupgrade.Admin').'</strong>';
                $this->nextErrors[] = '<strong>'.$this->trans('[ERROR] No response from Addons server.', array(), 'Modules.Autoupgrade.Admin').'</strong>';
                $this->warning_exists = 1;
            }
        }

        $isUpgraded = $this->moduleAdapter->getModuleDataUpdater()->upgrade($name);

        if (!$isUpgraded) {
            $this->nextQuickInfo[] = '<strong>'.$this->trans('[WARNING] Error when trying to upgrade module %s.', array($name), 'Modules.Autoupgrade.Admin').'</strong>';
            $this->warning_exists = 1;
        }

        return true;
    }

    public function ajaxProcessUpgradeDb()
    {
        $this->nextParams = $this->currentParams;
        if (!$this->doUpgrade()) {
            $this->next = 'error';
            $this->next_desc = $this->trans('Error during database upgrade. You may need to restore your database.', array(), 'Modules.Autoupgrade.Admin');
            return false;
        }
        $this->next = 'upgradeModules';
        $this->next_desc = $this->trans('Database upgraded. Now upgrading your Addons modules...', array(), 'Modules.Autoupgrade.Admin');
        return true;
    }

    /**
     * Clean the database from unwanted entires
     *
     * @return void
     */
    public function ajaxProcessCleanDatabase()
    {
        global $warningExists;

        /* Clean tabs order */
        foreach ($this->db->ExecuteS('SELECT DISTINCT id_parent FROM '._DB_PREFIX_.'tab') as $parent) {
            $i = 1;
            foreach ($this->db->ExecuteS('SELECT id_tab FROM '._DB_PREFIX_.'tab WHERE id_parent = '.(int)$parent['id_parent'].' ORDER BY IF(class_name IN ("AdminHome", "AdminDashboard"), 1, 2), position ASC') as $child) {
                $this->db->Execute('UPDATE '._DB_PREFIX_.'tab SET position = '.(int)($i++).' WHERE id_tab = '.(int)$child['id_tab'].' AND id_parent = '.(int)$parent['id_parent']);
            }
        }

        /* Clean configuration integrity */
        $this->db->Execute('DELETE FROM `'._DB_PREFIX_.'configuration_lang` WHERE (`value` IS NULL AND `date_upd` IS NULL) OR `value` LIKE ""', false);

        $this->status = 'ok';
        $this->next = 'upgradeComplete';
        $this->next_desc = $this->trans('The database has been cleaned.', array(), 'Modules.Autoupgrade.Admin');
        $this->nextQuickInfo[] = $this->trans('The database has been cleaned.', array(), 'Modules.Autoupgrade.Admin');
    }

    /**
     * This function now replaces doUpgrade.php or upgrade.php
     *
     * @return void
     */
    public function doUpgrade()
    {
        // Initialize
        // setting the memory limit to 128M only if current is lower
        $memory_limit = ini_get('memory_limit');
        if ((substr($memory_limit, -1) != 'G')
            && ((substr($memory_limit, -1) == 'M' and substr($memory_limit, 0, -1) < 128)
                || is_numeric($memory_limit) and (intval($memory_limit) < 131072))
        ) {
            @ini_set('memory_limit', '128M');
        }

        /* Redefine REQUEST_URI if empty (on some webservers...) */
        if (!isset($_SERVER['REQUEST_URI']) || empty($_SERVER['REQUEST_URI'])) {
            if (!isset($_SERVER['SCRIPT_NAME']) && isset($_SERVER['SCRIPT_FILENAME'])) {
                $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_FILENAME'];
            }
            if (isset($_SERVER['SCRIPT_NAME'])) {
                if (basename($_SERVER['SCRIPT_NAME']) == 'index.php' && empty($_SERVER['QUERY_STRING'])) {
                    $_SERVER['REQUEST_URI'] = dirname($_SERVER['SCRIPT_NAME']).'/';
                } else {
                    $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
                    if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
                        $_SERVER['REQUEST_URI'] .= '?'.$_SERVER['QUERY_STRING'];
                    }
                }
            }
        }
        $_SERVER['REQUEST_URI'] = str_replace('//', '/', $_SERVER['REQUEST_URI']);

        define('INSTALL_VERSION', $this->install_version);
        // 1.4
        define('INSTALL_PATH', realpath($this->latestRootDir.DIRECTORY_SEPARATOR.'install'));
        // 1.5 ...
        define('_PS_INSTALL_PATH_', INSTALL_PATH.DIRECTORY_SEPARATOR);
        // 1.6
        if (!defined('_PS_CORE_DIR_')) {
            define('_PS_CORE_DIR_', _PS_ROOT_DIR_);
        }


        define('PS_INSTALLATION_IN_PROGRESS', true);
        define('SETTINGS_FILE_PHP', $this->prodRootDir . '/app/config/parameters.php');
        define('SETTINGS_FILE_YML', $this->prodRootDir . '/app/config/parameters.yml');
        define('DEFINES_FILE', $this->prodRootDir .'/config/defines.inc.php');
        define('INSTALLER__PS_BASE_URI', substr($_SERVER['REQUEST_URI'], 0, -1 * (strlen($_SERVER['REQUEST_URI']) - strrpos($_SERVER['REQUEST_URI'], '/')) - strlen(substr(dirname($_SERVER['REQUEST_URI']), strrpos(dirname($_SERVER['REQUEST_URI']), '/')+1))));
        //	define('INSTALLER__PS_BASE_URI_ABSOLUTE', 'http://'.ToolsInstall::getHttpHost(false, true).INSTALLER__PS_BASE_URI);

        // XML Header
        // header('Content-Type: text/xml');

        $filePrefix = 'PREFIX_';
        $engineType = 'ENGINE_TYPE';

        $mysqlEngine = (defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'MyISAM');

        if (function_exists('date_default_timezone_set')) {
            date_default_timezone_set('Europe/Paris');
        }

        // if _PS_ROOT_DIR_ is defined, use it instead of "guessing" the module dir.
        if (defined('_PS_ROOT_DIR_') and !defined('_PS_MODULE_DIR_')) {
            define('_PS_MODULE_DIR_', _PS_ROOT_DIR_.'/modules/');
        } elseif (!defined('_PS_MODULE_DIR_')) {
            define('_PS_MODULE_DIR_', INSTALL_PATH.'/../modules/');
        }

        $upgrade_dir_php = 'upgrade/php';
        if (!file_exists(INSTALL_PATH.DIRECTORY_SEPARATOR.$upgrade_dir_php)) {
            $upgrade_dir_php = 'php';
            if (!file_exists(INSTALL_PATH.DIRECTORY_SEPARATOR.$upgrade_dir_php)) {
                $this->next = 'error';
                $this->next_desc = $this->trans('/install/upgrade/php directory is missing in archive or directory', array(), 'Modules.Autoupgrade.Admin');
                $this->nextQuickInfo[] = $this->trans('/install/upgrade/php directory is missing in archive or directory', array(), 'Modules.Autoupgrade.Admin');
                $this->nextErrors[] = $this->trans('/install/upgrade/php directory is missing in archive or directory.', array(), 'Modules.Autoupgrade.Admin');
                return false;
            }
        }
        define('_PS_INSTALLER_PHP_UPGRADE_DIR_',  INSTALL_PATH.DIRECTORY_SEPARATOR.$upgrade_dir_php.DIRECTORY_SEPARATOR);

        //old version detection
        global $oldversion, $logger;
        $oldversion = false;

        if (!file_exists(SETTINGS_FILE_PHP)) {
            $this->next = 'error';
            $this->nextQuickInfo[] = $this->trans('The app/config/parameters.php file was not found.', array(), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('The app/config/parameters.php file was not found.', array(), 'Modules.Autoupgrade.Admin');
            return false;
        }
        if (!file_exists(SETTINGS_FILE_YML)) {
            $this->next = 'error';
            $this->nextQuickInfo[] = $this->trans('The app/config/parameters.yml file was not found.', array(), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('The app/config/parameters.yml file was not found.', array(), 'Modules.Autoupgrade.Admin');
            return false;
        }

        $oldversion = Configuration::get('PS_VERSION_DB');

        if (!defined('__PS_BASE_URI__')) {
            define('__PS_BASE_URI__', realpath(dirname($_SERVER['SCRIPT_NAME'])).'/../../');
        }

        if (!defined('_THEMES_DIR_')) {
            define('_THEMES_DIR_', __PS_BASE_URI__.'themes/');
        }

        $versionCompare =  version_compare(INSTALL_VERSION, $oldversion);

        if ($versionCompare == '-1') {
            $this->next = 'error';
            $this->nextQuickInfo[] = $this->trans(
                'Current version: %oldversion%. Version to install: %newversion%.',
                array(
                    '%oldversion%' => $oldversion,
                    '%newversion%' => INSTALL_VERSION,
                ),
                'Modules.Autoupgrade.Admin'
            );
            $this->nextErrors[] = $this->trans(
                'Current version: %oldversion%. Version to install: %newversion%',
                array(
                    '%oldversion%' => $oldversion,
                    '%newversion%' => INSTALL_VERSION,
                ),
                'Modules.Autoupgrade.Admin'
            );
            $this->nextQuickInfo[] = $this->trans('[ERROR] Version to install is too old.', array(), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('[ERROR] Version to install is too old.', array(), 'Modules.Autoupgrade.Admin');
            return false;
        } elseif ($versionCompare == 0) {
            $this->next = 'error';
            $this->nextQuickInfo[] = $this->trans('You already have the %s version.', array(INSTALL_VERSION), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('You already have the %s version.', array(INSTALL_VERSION), 'Modules.Autoupgrade.Admin');
            return false;
        } elseif ($versionCompare === false) {
            $this->next = 'error';
            $this->nextQuickInfo[] = $this->trans('There is no older version. Did you delete or rename the app/config/parameters.php file?', array(), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('There is no older version. Did you delete or rename the app/config/parameters.php file?', array(), 'Modules.Autoupgrade.Admin');
            return false;
        }

        //check DB access
        $this->db;
        error_reporting(E_ALL);
        $resultDB = Db::checkConnection(_DB_SERVER_, _DB_USER_, _DB_PASSWD_, _DB_NAME_);
        if ($resultDB !== 0) {
            // $logger->logError('Invalid database configuration.');
            $this->next = 'error';
            $this->nextQuickInfo[] = $this->trans('Invalid database configuration', array(), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('Invalid database configuration', array(), 'Modules.Autoupgrade.Admin');
            return false;
        }

        //custom sql file creation
        $upgradeFiles = array();

        $upgrade_dir_sql = INSTALL_PATH.'/upgrade/sql';
        // if 1.4;
        if (!file_exists($upgrade_dir_sql)) {
            $upgrade_dir_sql = INSTALL_PATH.'/sql/upgrade';
        }

        if (!file_exists($upgrade_dir_sql)) {
            $this->next = 'error';
            $this->next_desc = $this->trans('Unable to find upgrade directory in the installation path.', array(), 'Modules.Autoupgrade.Admin');
            return false;
        }

        if ($handle = opendir($upgrade_dir_sql)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != '.' and $file != '..') {
                    $upgradeFiles[] = str_replace(".sql", "", $file);
                }
            }
            closedir($handle);
        }
        if (empty($upgradeFiles)) {
            $this->next = 'error';
            $this->nextQuickInfo[] = $this->trans('Cannot find the SQL upgrade files. Please check that the %s folder is not empty.', array($upgrade_dir_sql), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('Cannot find the SQL upgrade files. Please check that the %s folder is not empty.', array($upgrade_dir_sql), 'Modules.Autoupgrade.Admin');
            // fail 31
            return false;
        }
        natcasesort($upgradeFiles);
        $neededUpgradeFiles = array();

        $arrayVersion = explode('.', $oldversion);
        $versionNumbers = count($arrayVersion);
        if ($versionNumbers != 4) {
            $arrayVersion = array_pad($arrayVersion, 4, '0');
        }

        $oldversion = implode('.', $arrayVersion);

        foreach ($upgradeFiles as $version) {
            if (version_compare($version, $oldversion) == 1 && version_compare(INSTALL_VERSION, $version) != -1) {
                $neededUpgradeFiles[] = $version;
            }
        }

        if (strpos(INSTALL_VERSION, '.') === false) {
            $this->nextQuickInfo[] = $this->trans('%s is not a valid version number.', array(INSTALL_VERSION), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('%s is not a valid version number.', array(INSTALL_VERSION), 'Modules.Autoupgrade.Admin');
            return false;
        }

        $sqlContentVersion = array();
        if ($this->deactivateCustomModule) {
            $this->moduleAdapter->disableNonNativeModules();
        }

        foreach ($neededUpgradeFiles as $version) {
            $file = $upgrade_dir_sql.DIRECTORY_SEPARATOR.$version.'.sql';
            if (!file_exists($file)) {
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->trans('Error while loading SQL upgrade file "%s.sql".', array($version), 'Modules.Autoupgrade.Admin');
                $this->nextErrors[] = $this->trans('Error while loading SQL upgrade file "%s.sql".', array($version), 'Modules.Autoupgrade.Admin');
                return false;
                $logger->logError('Error while loading SQL upgrade file.');
            }
            if (!$sqlContent = file_get_contents($file)."\n") {
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->trans('Error while loading SQL upgrade file %s.', array($version), 'Modules.Autoupgrade.Admin');
                $this->nextErrors[] = $this->trans('Error while loading sql SQL file %s.', array($version), 'Modules.Autoupgrade.Admin');
                return false;
                $logger->logError(sprintf('Error while loading sql upgrade file %s.', $version));
            }
            $sqlContent = str_replace(array($filePrefix, $engineType), array(_DB_PREFIX_, $mysqlEngine), $sqlContent);
            $sqlContent = preg_split("/;\s*[\r\n]+/", $sqlContent);
            $sqlContentVersion[$version] = $sqlContent;
        }

        //sql file execution
        global $requests, $warningExist;
        $requests = '';
        $warningExist = false;

        $request = '';

        foreach ($sqlContentVersion as $upgrade_file => $sqlContent) {
            foreach ($sqlContent as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    /* If php code have to be executed */
                    if (strpos($query, '/* PHP:') !== false) {
                        /* Parsing php code */
                        $pos = strpos($query, '/* PHP:') + strlen('/* PHP:');
                        $phpString = substr($query, $pos, strlen($query) - $pos - strlen(' */;'));
                        $php = explode('::', $phpString);
                        preg_match('/\((.*)\)/', $phpString, $pattern);
                        $paramsString = trim($pattern[0], '()');
                        preg_match_all('/([^,]+),? ?/', $paramsString, $parameters);
                        if (isset($parameters[1])) {
                            $parameters = $parameters[1];
                        } else {
                            $parameters = array();
                        }
                        if (is_array($parameters)) {
                            foreach ($parameters as &$parameter) {
                                $parameter = str_replace('\'', '', $parameter);
                            }
                        }

                        // reset phpRes to a null value
                        $phpRes = null;
                        /* Call a simple function */
                        if (strpos($phpString, '::') === false) {
                            $func_name = str_replace($pattern[0], '', $php[0]);
                            if (version_compare(INSTALL_VERSION, '1.5.5.0', '=') && $func_name == 'fix_download_product_feature_active') {
                                continue;
                            }

                            if (!file_exists(_PS_INSTALLER_PHP_UPGRADE_DIR_.strtolower($func_name).'.php')) {
                                $this->nextQuickInfo[] = '<div class="upgradeDbError">[ERROR] '.$upgrade_file.' PHP - missing file '.$query.'</div>';
                                $this->nextErrors[] = '[ERROR] '.$upgrade_file.' PHP - missing file '.$query;
                                $warningExist = true;
                            } else {
                                require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.strtolower($func_name).'.php');
                                $phpRes = call_user_func_array($func_name, $parameters);
                            }
                        }
                        /* Or an object method */
                        else {
                            $func_name = array($php[0], str_replace($pattern[0], '', $php[1]));
                            $this->nextQuickInfo[] = '<div class="upgradeDbError">[ERROR] '.$upgrade_file.' PHP - Object Method call is forbidden ( '.$php[0].'::'.str_replace($pattern[0], '', $php[1]).')</div>';
                            $this->nextErrors[] = '[ERROR] '.$upgrade_file.' PHP - Object Method call is forbidden ('.$php[0].'::'.str_replace($pattern[0], '', $php[1]).')';
                            $warningExist = true;
                        }

                        if (isset($phpRes) && (is_array($phpRes) && !empty($phpRes['error'])) || $phpRes === false) {
                            // $this->next = 'error';
                            $this->nextQuickInfo[] = '
								<div class="upgradeDbError">
									[ERROR] PHP '.$upgrade_file.' '.$query."\n".'
									'.(empty($phpRes['error']) ? '' : $phpRes['error']."\n").'
									'.(empty($phpRes['msg']) ? '' : ' - '.$phpRes['msg']."\n").'
								</div>';
                            $this->nextErrors[] = '
								[ERROR] PHP '.$upgrade_file.' '.$query."\n".'
								'.(empty($phpRes['error']) ? '' : $phpRes['error']."\n").'
								'.(empty($phpRes['msg']) ? '' : ' - '.$phpRes['msg']."\n");
                            $warningExist = true;
                        } else {
                            $this->nextQuickInfo[] = '<div class="upgradeDbOk">[OK] PHP '.$upgrade_file.' : '.$query.'</div>';
                        }
                        if (isset($phpRes)) {
                            unset($phpRes);
                        }
                    } else {
                        if (strstr($query, 'CREATE TABLE') !== false) {
                            $pattern = '/CREATE TABLE.*[`]*'._DB_PREFIX_.'([^`]*)[`]*\s\(/';
                            preg_match($pattern, $query, $matches);
                            ;
                            if (isset($matches[1]) && $matches[1]) {
                                $drop = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.$matches[1].'`;';
                                $result = $this->db->execute($drop, false);
                                if ($result) {
                                    $this->nextQuickInfo[] = '<div class="upgradeDbOk">'.$this->trans('[DROP] SQL %s table has been dropped.', array('`'._DB_PREFIX_.$matches[1].'`'), 'Modules.Autoupgrade.Admin').'</div>';
                                }
                            }
                        }
                        $result = $this->db->execute($query, false);
                        if (!$result) {
                            $error = $this->db->getMsgError();
                            $error_number = $this->db->getNumberError();
                            $this->nextQuickInfo[] = '
								<div class="upgradeDbError">
								[WARNING] SQL '.$upgrade_file.'
								'.$error_number.' in '.$query.': '.$error.'</div>';

                            $duplicates = array('1050', '1054', '1060', '1061', '1062', '1091');
                            if (!in_array($error_number, $duplicates)) {
                                $this->nextErrors[] = 'SQL '.$upgrade_file.' '.$error_number.' in '.$query.': '.$error;
                                $warningExist = true;
                            }
                        } else {
                            $this->nextQuickInfo[] = '<div class="upgradeDbOk">[OK] SQL '.$upgrade_file.' '.$query.'</div>';
                        }
                    }
                    if (isset($query)) {
                        unset($query);
                    }
                }
            }
        }
        if ($this->next == 'error') {
            $this->next_desc = $this->trans('An error happened during the database upgrade.', array(), 'Modules.Autoupgrade.Admin');
            return false;
        }

        if (version_compare(INSTALL_VERSION, '1.7.1.1', '>=')) {
            $schemaUpgrade = new \PrestaShopBundle\Service\Database\Upgrade();
            $outputCommand = 'prestashop:schema:update-without-foreign';
        } else {
            $schemaUpgrade = new \PrestaShopBundle\Service\Cache\Refresh();
            $outputCommand = 'doctrine:schema:update';
        }

        $schemaUpgrade->addDoctrineSchemaUpdate();
        $output = $schemaUpgrade->execute();

        if (0 !== $output[$outputCommand]['exitCode']) {
            $msgErrors = explode("\n", $output[$outputCommand]['output']);
            $this->nextErrors[] = $this->trans('Error upgrading Doctrine schema', array(), 'Modules.Autoupgrade.Admin');
            $this->nextQuickInfo[] = $msgErrors;
            $this->next_desc = $msgErrors;
            return false;
        }

        $this->nextQuickInfo[] = $this->trans('Database upgrade OK', array(), 'Modules.Autoupgrade.Admin'); // no error!

        // Settings updated, compile and cache directories must be emptied
        $arrayToClean[] = $this->prodRootDir.'/app/cache/';
        $arrayToClean[] = $this->prodRootDir.'/cache/smarty/cache/';
        $arrayToClean[] = $this->prodRootDir.'/cache/smarty/compile/';

        foreach ($arrayToClean as $dir) {
            if (!file_exists($dir)) {
                $this->nextQuickInfo[] = $this->trans('[SKIP] directory "%s" does not exist and cannot be emptied.', array(str_replace($this->prodRootDir, '', $dir)), 'Modules.Autoupgrade.Admin');
                continue;
            } else {
                foreach (scandir($dir) as $file) {
                    if ($file[0] != '.' && $file != 'index.php' && $file != '.htaccess') {
                        if (is_file($dir.$file)) {
                            unlink($dir.$file);
                        } elseif (is_dir($dir.$file.DIRECTORY_SEPARATOR)) {
                            self::deleteDirectory($dir.$file.DIRECTORY_SEPARATOR);
                        }
                        $this->nextQuickInfo[] = $this->trans('[CLEANING CACHE] File %s removed', array($file), 'Modules.Autoupgrade.Admin');
                    }
                }
            }
        }

        $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET `name` = \'PS_LEGACY_IMAGES\' WHERE name LIKE \'0\' AND `value` = 1');
        $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET `value` = 0 WHERE `name` LIKE \'PS_LEGACY_IMAGES\'');
        if ($this->db->getValue('SELECT COUNT(id_product_download) FROM `'._DB_PREFIX_.'product_download` WHERE `active` = 1') > 0) {
            $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET `value` = 1 WHERE `name` LIKE \'PS_VIRTUAL_PROD_FEATURE_ACTIVE\'');
        }

        if (defined('_THEME_NAME_') && $this->updateDefaultTheme && 'classic' === _THEME_NAME_) {
            $separator = addslashes(DIRECTORY_SEPARATOR);
            $file = _PS_ROOT_DIR_.$separator.'themes'.$separator._THEME_NAME_.$separator.'cache'.$separator;
            if (file_exists($file)) {
                foreach (scandir($file) as $cache) {
                    if ($cache[0] != '.' && $cache != 'index.php' && $cache != '.htaccess' && file_exists($file.$cache) && !is_dir($file.$cache)) {
                        if (file_exists($dir.$cache)) {
                            unlink($file.$cache);
                        }
                    }
                }
            }
        }

        // Upgrade languages
        if (!defined('_PS_TOOL_DIR_')) {
            define('_PS_TOOL_DIR_', _PS_ROOT_DIR_.'/tools/');
        }
        if (!defined('_PS_TRANSLATIONS_DIR_')) {
            define('_PS_TRANSLATIONS_DIR_', _PS_ROOT_DIR_.'/translations/');
        }
        if (!defined('_PS_MODULES_DIR_')) {
            define('_PS_MODULES_DIR_', _PS_ROOT_DIR_.'/modules/');
        }
        if (!defined('_PS_MAILS_DIR_')) {
            define('_PS_MAILS_DIR_', _PS_ROOT_DIR_.'/mails/');
        }

        $langs = $this->db->executeS('SELECT * FROM `'._DB_PREFIX_.'lang` WHERE `active` = 1');

        if (is_array($langs)) {
            foreach ($langs as $lang) {
                $isoCode = $lang['iso_code'];

                if (Validate::isLangIsoCode($isoCode)) {
                    $errorsLanguage = array();

                    Language::downloadLanguagePack($isoCode, _PS_VERSION_, $errorsLanguage);

                    $lang_pack = Language::getLangDetails($isoCode);
                    Language::installSfLanguagePack($lang_pack['locale'], $errorsLanguage);

                    if (!$this->keepMails) {
                        Language::installEmailsLanguagePack($lang_pack, $errorsLanguage);
                    }

                    if (empty($errorsLanguage)) {
                        Language::loadLanguages();

                        // TODO: Update AdminTranslationsController::addNewTabs to install tabs translated

                        $cldrUpdate = new \PrestaShop\PrestaShop\Core\Cldr\Update(_PS_TRANSLATIONS_DIR_);
                        $cldrUpdate->fetchLocale(Language::getLocaleByIso($isoCode));
                    } else {
                        $this->nextErrors[] = $this->trans('Error updating translations', array(), 'Modules.Autoupgrade.Admin');
                        $this->nextQuickInfo[] = $this->trans('Error updating translations', array(), 'Modules.Autoupgrade.Admin');
                        $this->next_desc = $this->trans('Error updating translations', array(), 'Modules.Autoupgrade.Admin');
                        return false;
                    }
                }
            }
        }

        require_once(_PS_ROOT_DIR_.'/src/Core/Foundation/Database/EntityInterface.php');

        if (file_exists(_PS_ROOT_DIR_.'/classes/Tools.php')) {
            require_once(_PS_ROOT_DIR_.'/classes/Tools.php');
        }
        if (!class_exists('Tools2', false) and class_exists('ToolsCore')) {
            eval('class Tools2 extends ToolsCore{}');
        }

        if (class_exists('Tools2') && method_exists('Tools2', 'generateHtaccess')) {
            $url_rewrite = (bool)$this->db->getvalue('SELECT `value` FROM `'._DB_PREFIX_.'configuration` WHERE name=\'PS_REWRITING_SETTINGS\'');

            if (!defined('_MEDIA_SERVER_1_')) {
                define('_MEDIA_SERVER_1_', '');
            }

            if (!defined('_PS_USE_SQL_SLAVE_')) {
                define('_PS_USE_SQL_SLAVE_', false);
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/ObjectModel.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/ObjectModel.php');
            }
            if (!class_exists('ObjectModel', false) and class_exists('ObjectModelCore')) {
                eval('abstract class ObjectModel extends ObjectModelCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/Configuration.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/Configuration.php');
            }
            if (!class_exists('Configuration', false) and class_exists('ConfigurationCore')) {
                eval('class Configuration extends ConfigurationCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/cache/Cache.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/cache/Cache.php');
            }
            if (!class_exists('Cache', false) and class_exists('CacheCore')) {
                eval('abstract class Cache extends CacheCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/PrestaShopCollection.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/PrestaShopCollection.php');
            }
            if (!class_exists('PrestaShopCollection', false) and class_exists('PrestaShopCollectionCore')) {
                eval('class PrestaShopCollection extends PrestaShopCollectionCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/shop/ShopUrl.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/shop/ShopUrl.php');
            }
            if (!class_exists('ShopUrl', false) and class_exists('ShopUrlCore')) {
                eval('class ShopUrl extends ShopUrlCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/shop/Shop.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/shop/Shop.php');
            }
            if (!class_exists('Shop', false) and class_exists('ShopCore')) {
                eval('class Shop extends ShopCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/Translate.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/Translate.php');
            }
            if (!class_exists('Translate', false) and class_exists('TranslateCore')) {
                eval('class Translate extends TranslateCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/module/Module.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/module/Module.php');
            }
            if (!class_exists('Module', false) and class_exists('ModuleCore')) {
                eval('class Module extends ModuleCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/Validate.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/Validate.php');
            }
            if (!class_exists('Validate', false) and class_exists('ValidateCore')) {
                eval('class Validate extends ValidateCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/Language.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/Language.php');
            }
            if (!class_exists('Language', false) and class_exists('LanguageCore')) {
                eval('class Language extends LanguageCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/Tab.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/Tab.php');
            }
            if (!class_exists('Tab', false) and class_exists('TabCore')) {
                eval('class Tab extends TabCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/Dispatcher.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/Dispatcher.php');
            }
            if (!class_exists('Dispatcher', false) and class_exists('DispatcherCore')) {
                eval('class Dispatcher extends DispatcherCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/Hook.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/Hook.php');
            }
            if (!class_exists('Hook', false) and class_exists('HookCore')) {
                eval('class Hook extends HookCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/Context.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/Context.php');
            }
            if (!class_exists('Context', false) and class_exists('ContextCore')) {
                eval('class Context extends ContextCore{}');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/Group.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/Group.php');
            }
            if (!class_exists('Group', false) and class_exists('GroupCore')) {
                eval('class Group extends GroupCore{}');
            }

            Tools2::generateHtaccess(null, $url_rewrite);
        }

        $path = $this->adminDir.DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR.'default'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR.'controllers'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'header.tpl';
        if (file_exists($path)) {
            unlink($path);
        }

        if (file_exists(_PS_ROOT_DIR_.'/app/cache/dev/class_index.php')) {
            unlink(_PS_ROOT_DIR_.'/app/cache/dev/class_index.php');
        }
        if (file_exists(_PS_ROOT_DIR_.'/app/cache/prod/class_index.php')) {
            unlink(_PS_ROOT_DIR_.'/app/cache/prod/class_index.php');
        }

        // Clear XML files
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/blog-fr.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/blog-fr.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/default_country_modules_list.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/default_country_modules_list.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/modules_list.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/modules_list.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/modules_native_addons.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/modules_native_addons.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/must_have_modules_list.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/must_have_modules_list.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/tab_modules_list.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/tab_modules_list.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/trusted_modules_list.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/trusted_modules_list.xml');
        }
        if (file_exists(_PS_ROOT_DIR_.'/config/xml/untrusted_modules_list.xml')) {
            unlink(_PS_ROOT_DIR_.'/config/xml/untrusted_modules_list.xml');
        }

        if ($this->deactivateCustomModule) {
            $exist = $this->db->getValue('SELECT `id_configuration` FROM `'._DB_PREFIX_.'configuration` WHERE `name` LIKE \'PS_DISABLE_OVERRIDES\'');
            if ($exist) {
                $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value = 1 WHERE `name` LIKE \'PS_DISABLE_OVERRIDES\'');
            } else {
                $this->db->execute('INSERT INTO `'._DB_PREFIX_.'configuration` (name, value, date_add, date_upd) VALUES ("PS_DISABLE_OVERRIDES", 1, NOW(), NOW())');
            }

            if (file_exists(_PS_ROOT_DIR_.'/classes/PrestaShopAutoload.php')) {
                require_once(_PS_ROOT_DIR_.'/classes/PrestaShopAutoload.php');
            }

            if (class_exists('PrestaShopAutoload') && method_exists('PrestaShopAutoload', 'generateIndex')) {
                PrestaShopAutoload::getInstance()->_include_override_path = false;
                PrestaShopAutoload::getInstance()->generateIndex();
            }
        }

        $themeManager = $this->getThemeManager();
        $themeName = ($this->changeToDefaultTheme ? 'classic' : _THEME_NAME_);

        $isThemeEnabled = $themeManager->enable($themeName);
        if (!$isThemeEnabled) {
            $themeErrors = $themeManager->getErrors($themeName);
            $this->nextQuickInfo[] = $themeErrors;
            $this->nextErrors[] = $themeErrors;
            $this->next_desc = $themeErrors;

            return false;
        } else {
            Tools::clearCache();
        }


        // delete cache filesystem if activated
        if (defined('_PS_CACHE_ENABLED_') && _PS_CACHE_ENABLED_) {
            $depth = (int)$this->db->getValue('SELECT value
				FROM '._DB_PREFIX_.'configuration
				WHERE name = "PS_CACHEFS_DIRECTORY_DEPTH"');
            if ($depth) {
                if (!defined('_PS_CACHEFS_DIRECTORY_')) {
                    define('_PS_CACHEFS_DIRECTORY_', $this->prodRootDir.'/cache/cachefs/');
                }
                self::deleteDirectory(_PS_CACHEFS_DIRECTORY_, false);
                if (class_exists('CacheFs', false)) {
                    self::createCacheFsDirectories((int)$depth);
                }
            }
        }

        $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value="0" WHERE name = "PS_HIDE_OPTIMIZATION_TIS"', false);
        $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value="1" WHERE name = "PS_NEED_REBUILD_INDEX"', false);
        $this->db->execute('UPDATE `'._DB_PREFIX_.'configuration` SET value="'.INSTALL_VERSION.'" WHERE name = "PS_VERSION_DB"', false);

        if ($warningExist) {
            $this->warning_exists = true;
            $this->nextQuickInfo[] = $this->trans('Warning detected during upgrade.', array(), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('Warning detected during upgrade.', array(), 'Modules.Autoupgrade.Admin');
            $this->next_desc = $this->trans('Warning detected during upgrade.', array(), 'Modules.Autoupgrade.Admin');
        } else {
            $this->next_desc = $this->trans('Database upgrade completed', array(), 'Modules.Autoupgrade.Admin');
        }

        return true;
    }

    /**
     * getTranslationFileType
     *
     * @param string $file filepath to check
     * @access public
     * @return string type of translation item
     */
    public function getTranslationFileType($file)
    {
        $type = false;
        // line shorter
        $separator = addslashes(DIRECTORY_SEPARATOR);
        $translation_dir = $separator.'translations'.$separator;

        $regex_module = '#'.$separator.'modules'.$separator.'.*'.$translation_dir.'('.implode('|', $this->installedLanguagesIso).')\.php#';

        if (preg_match($regex_module, $file)) {
            $type = 'module';
        } elseif (preg_match('#'.$translation_dir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'admin\.php#', $file)) {
            $type = 'back office';
        } elseif (preg_match('#'.$translation_dir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'errors\.php#', $file)) {
            $type = 'error message';
        } elseif (preg_match('#'.$translation_dir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'fields\.php#', $file)) {
            $type = 'field';
        } elseif (preg_match('#'.$translation_dir.'('.implode('|', $this->installedLanguagesIso).')'.$separator.'pdf\.php#', $file)) {
            $type = 'pdf';
        } elseif (preg_match('#'.$separator.'themes'.$separator.'(default|prestashop)'.$separator.'lang'.$separator.'('.implode('|', $this->installedLanguagesIso).')\.php#', $file)) {
            $type = 'front office';
        }

        return $type;
    }

    /**
     * return true if $file is a translation file
     *
     * @param string $file filepath (from prestashop root)
     * @access public
     * @return boolean
     */
    public function isTranslationFile($file)
    {
        if ($this->getTranslationFileType($file) !== false) {
            return true;
        }

        return false;
    }

    /**
     * merge the translations of $orig into $dest, according to the $type of translation file
     *
     * @param string $orig file from upgrade package
     * @param string $dest filepath of destination
     * @param string $type type of translation file (module, bo, fo, field, pdf, error)
     * @access public
     * @return boolean
     */
    public function mergeTranslationFile($orig, $dest, $type)
    {
        switch ($type) {
            case 'front office':
                $var_name = '_LANG';
                break;
            case 'back office':
                $var_name = '_LANGADM';
                break;
            case 'error message':
                $var_name = '_ERRORS';
                break;
            case 'field':
                $var_name = '_FIELDS';
                break;
            case 'module':
                $var_name = '_MODULE';
                // if current version is before 1.5.0.5, module has no translations dir
                if (version_compare(_PS_VERSION_, '1.5.0.5', '<') && (version_compare($this->install_version, '1.5.0.5', '>'))) {
                    $dest = str_replace(DIRECTORY_SEPARATOR.'translations', '', $dest);
                }

                break;
            case 'pdf':
                $var_name = '_LANGPDF';
                break;
            case 'mail':
                $var_name = '_LANGMAIL';
                break;
            default:
                return false;
        }

        if (!file_exists($orig)) {
            $this->nextQuickInfo[] = $this->trans('[NOTICE] File %s does not exist, merge skipped.', array($orig), 'Modules.Autoupgrade.Admin');
            return true;
        }
        include($orig);
        if (!isset($$var_name)) {
            $this->nextQuickInfo[] = $this->trans(
                '[WARNING] %variablename% variable missing in file %filename%. Merge skipped.',
                array(
                    '%variablename%' => $var_name,
                    '%filename%' => $orig,
                ),
                'Modules.Autoupgrade.Admin'
            );
            return true;
        }
        $var_orig = $$var_name;

        if (!file_exists($dest)) {
            $this->nextQuickInfo[] = $this->trans('[NOTICE] File %s does not exist, merge skipped.', array($dest), 'Modules.Autoupgrade.Admin');
            return false;
        }
        include($dest);
        if (!isset($$var_name)) {
            // in that particular case : file exists, but variable missing, we need to delete that file
            // (if not, this invalid file will be copied in /translations during upgradeDb process)
            if ('module' == $type && file_exists($dest)) {
                unlink($dest);
            }
            $this->nextQuickInfo[] = $this->trans(
                '[WARNING] %variablename% variable missing in file %filename%. File %filename% deleted and merge skipped.',
                array(
                    '%variablename%' => $var_name,
                    '%filename%' => $dest,
                ),
                'Modules.Autoupgrade.Admin'
            );
            return false;
        }
        $var_dest = $$var_name;

        $merge = array_merge($var_orig, $var_dest);

        if ($fd = fopen($dest, 'w')) {
            fwrite($fd, "<?php\n\nglobal \$".$var_name.";\n\$".$var_name." = array();\n");
            foreach ($merge as $k => $v) {
                if (get_magic_quotes_gpc()) {
                    $v = stripslashes($v);
                }
                if ('mail' == $type) {
                    fwrite($fd, '$'.$var_name.'[\''.$this->db->escape($k).'\'] = \''.$this->db->escape($v).'\';'."\n");
                } else {
                    fwrite($fd, '$'.$var_name.'[\''.$this->db->escape($k, true).'\'] = \''.$this->db->escape($v, true).'\';'."\n");
                }
            }
            fwrite($fd, "\n?>");
            fclose($fd);
        } else {
            return false;
        }

        return true;
    }

    /**
     * upgradeThisFile
     *
     * @param mixed $file
     * @return void
     */
    public function upgradeThisFile($file)
    {
        // translations_custom and mails_custom list are currently not used
        // later, we could handle customization with some kind of diff functions
        // for now, just copy $file in str_replace($this->latestRootDir,_PS_ROOT_DIR_)
        $orig = $this->latestRootDir.$file;
        $dest = $this->destUpgradePath.$file;

        if ($this->_skipFile($file, $dest, 'upgrade')) {
            $this->nextQuickInfo[] = $this->trans('%s ignored', array($file), 'Modules.Autoupgrade.Admin');
            return true;
        } else {
            if (is_dir($orig)) {
                // if $dest is not a directory (that can happen), just remove that file
                if (!is_dir($dest) and file_exists($dest)) {
                    unlink($dest);
                    $this->nextQuickInfo[] = $this->trans('[WARNING] File %1$s has been deleted.', array($file), 'Modules.Autoupgrade.Admin');
                }
                if (!file_exists($dest)) {
                    if (mkdir($dest)) {
                        $this->nextQuickInfo[] = $this->trans('Directory %1$s created.', array($file), 'Modules.Autoupgrade.Admin');
                        return true;
                    } else {
                        $this->next = 'error';
                        $this->nextQuickInfo[] = $this->trans('Error while creating directory %s.', array($dest), 'Modules.Autoupgrade.Admin');
                        $this->nextErrors[] = $this->next_desc = $this->trans('Error while creating directory %s.', array($dest), 'Modules.Autoupgrade.Admin');
                        return false;
                    }
                } else { // directory already exists
                    $this->nextQuickInfo[] = $this->trans('Directory %s already exists.', array($file), 'Modules.Autoupgrade.Admin');
                    return true;
                }
            } elseif (is_file($orig)) {
                if ($this->isTranslationFile($file) && file_exists($dest)) {
                    $type_trad = $this->getTranslationFileType($file);
                    $res = $this->mergeTranslationFile($orig, $dest, $type_trad);
                    if ($res) {
                        $this->nextQuickInfo[] = $this->trans('[TRANSLATION] The translation files have been merged into file %s.', array($dest), 'Modules.Autoupgrade.Admin');
                        return true;
                    } else {
                        $this->nextQuickInfo[] = $this->trans(
                            '[TRANSLATION] The translation files have not been merged into file %filename%. Switch to copy %filename%.',
                            array('%filename%' => $dest),
                            'Modules.Autoupgrade.Admin'
                        );
                        $this->nextErrors[] = $this->trans(
                            '[TRANSLATION] The translation files have not been merged into file %filename%. Switch to copy %filename%.',
                            array('%filename%' => $dest),
                            'Modules.Autoupgrade.Admin'
                        );
                    }
                }

                // upgrade exception were above. This part now process all files that have to be upgraded (means to modify or to remove)
                // delete before updating (and this will also remove deprecated files)
                if (copy($orig, $dest)) {
                    $this->nextQuickInfo[] = $this->trans('Copied %1$s.', array($file), 'Modules.Autoupgrade.Admin');
                    return true;
                } else {
                    $this->next = 'error';
                    $this->nextQuickInfo[] = $this->trans('Error while copying file %s', array($file), 'Modules.Autoupgrade.Admin');
                    $this->nextErrors[] = $this->next_desc = $this->trans('Error while copying file %s', array($file), 'Modules.Autoupgrade.Admin');
                    return false;
                }
            } elseif (is_file($dest)) {
                if (file_exists($dest)) {
                    unlink($dest);
                }
                $this->nextQuickInfo[] = sprintf('removed file %1$s.', $file);
                return true;
            } elseif (is_dir($dest)) {
                if (strpos($dest, DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR) === false) {
                    self::deleteDirectory($dest, true);
                }
                $this->nextQuickInfo[] = sprintf('removed dir %1$s.', $file);
                return true;
            } else {
                return true;
            }
        }
    }

    public function ajaxProcessRollback()
    {
        // 1st, need to analyse what was wrong.
        $this->nextParams = $this->currentParams;
        $this->restoreFilesFilename = $this->restoreName;
        if (!empty($this->restoreName)) {
            $files = scandir($this->backupPath);
            // find backup filenames, and be sure they exists
            foreach ($files as $file) {
                if (preg_match('#'.preg_quote('auto-backupfiles_'.$this->restoreName).'#', $file)) {
                    $this->restoreFilesFilename = $file;
                    break;
                }
            }
            if (!is_file($this->backupPath.DIRECTORY_SEPARATOR.$this->restoreFilesFilename)) {
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->trans('[ERROR] file %s is missing : unable to restore files. Operation aborted.', array($this->restoreFilesFilename), 'Modules.Autoupgrade.Admin');
                $this->nextErrors[] = $this->next_desc = $this->trans('[ERROR] File %s is missing: unable to restore files. Operation aborted.', array($this->restoreFilesFilename), 'Modules.Autoupgrade.Admin');
                return false;
            }
            $files = scandir($this->backupPath.DIRECTORY_SEPARATOR.$this->restoreName);
            foreach ($files as $file) {
                if (preg_match('#auto-backupdb_[0-9]{6}_'.preg_quote($this->restoreName).'#', $file)) {
                    $this->restoreDbFilenames[] = $file;
                }
            }

            // order files is important !
            if (is_array($this->restoreDbFilenames)) {
                sort($this->restoreDbFilenames);
            }
            if (count($this->restoreDbFilenames) == 0) {
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->trans('[ERROR] No backup database files found: it would be impossible to restore the database. Operation aborted.', array(), 'Modules.Autoupgrade.Admin');
                $this->nextErrors[] = $this->next_desc = $this->trans('[ERROR] No backup database files found: it would be impossible to restore the database. Operation aborted.', array(), 'Modules.Autoupgrade.Admin');
                return false;
            }

            $this->next = 'restoreFiles';
            $this->next_desc = $this->trans('Restoring files ...', array(), 'Modules.Autoupgrade.Admin');
            // remove tmp files related to restoreFiles
            if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->fromArchiveFileList)) {
                unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->fromArchiveFileList);
            }
            if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRemoveFileList)) {
                unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRemoveFileList);
            }
        } else {
            $this->next = 'noRollbackFound';
        }
    }

    public function ajaxProcessNoRollbackFound()
    {
        $this->next_desc = $this->trans('Nothing to restore', array(), 'Modules.Autoupgrade.Admin');
        $this->next = 'rollbackComplete';
    }

    /**
     * ajaxProcessRestoreFiles restore the previously saved files,
     * and delete files that weren't archived
     *
     * @return boolean true if succeed
     */
    public function ajaxProcessRestoreFiles()
    {
        // loop
        $this->next = 'restoreFiles';
        if (!file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->fromArchiveFileList)
            || !file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRemoveFileList)) {
            // cleanup current PS tree
            $fromArchive = $this->_listArchivedFiles($this->backupPath.DIRECTORY_SEPARATOR.$this->restoreFilesFilename);
            foreach ($fromArchive as $k => $v) {
                $fromArchive[DIRECTORY_SEPARATOR.$v] = DIRECTORY_SEPARATOR.$v;
            }

            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->fromArchiveFileList, base64_encode(serialize($fromArchive)));
            // get list of files to remove
            $toRemove = $this->_listFilesToRemove();
            $toRemoveOnly = array();

            // let's reverse the array in order to make possible to rmdir
            // remove fullpath. This will be added later in the loop.
            // we do that for avoiding fullpath to be revealed in a text file
            foreach ($toRemove as $k => $v) {
                $vfile = str_replace($this->prodRootDir, '', $v);
                $toRemove[] = str_replace($this->prodRootDir, '', $vfile);

                if (!isset($fromArchive[$vfile]) && is_file($v)) {
                    $toRemoveOnly[$vfile] = str_replace($this->prodRootDir, '', $vfile);
                }
            }

            $this->nextQuickInfo[] = $this->trans('%s file(s) will be removed before restoring the backup files.', array(count($toRemoveOnly)), 'Modules.Autoupgrade.Admin');
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRemoveFileList, base64_encode(serialize($toRemoveOnly)));

            if ($fromArchive === false || $toRemove === false) {
                if (!$fromArchive) {
                    $this->nextQuickInfo[] = $this->trans('[ERROR] Backup file %s does not exist.', array($this->fromArchiveFileList), 'Modules.Autoupgrade.Admin');
                    $this->nextErrors[] = $this->trans('[ERROR] Backup file %s does not exist.', array($this->fromArchiveFileList), 'Modules.Autoupgrade.Admin');
                }
                if (!$toRemove) {
                    $this->nextQuickInfo[] = $this->trans('[ERROR] File "%s" does not exist.', array($this->toRemoveFileList), 'Modules.Autoupgrade.Admin');
                    $this->nextErrors[] = $this->trans('[ERROR] File "%s" does not exist.', array($this->toRemoveFileList), 'Modules.Autoupgrade.Admin');
                }
                $this->next_desc = $this->trans('Unable to remove upgraded files.', array(), 'Modules.Autoupgrade.Admin');
                $this->next = 'error';
                return false;
            }
        }

        if (!empty($fromArchive)) {
            $filepath = $this->backupPath.DIRECTORY_SEPARATOR.$this->restoreFilesFilename;
            $destExtract = $this->prodRootDir;

            if ($this->ZipExtract($filepath, $destExtract)) {
                if (!empty($toRemoveOnly)) {
                    foreach ($toRemoveOnly as $fileToRemove) {
                        @unlink($this->prodRootDir . $fileToRemove);
                    }
                }

                $this->next = 'restoreDb';
                $this->next_desc = $this->trans('Files restored. Now restoring database...', array(), 'Modules.Autoupgrade.Admin');
                $this->nextQuickInfo[] = $this->trans('Files restored.', array(), 'Modules.Autoupgrade.Admin');
                return true;
            } else {
                $this->next = 'error';
                $this->next_desc = $this->trans(
                    'Unable to extract file %filename% into directory %directoryname% .',
                    array(
                        '%filename%' => $filepath,
                        '%directoryname%' => $destExtract,
                    ),
                    'Modules.Autoupgrade.Admin'
                );
                return false;
            }
        }
    }

    public function isDirEmpty($dir, $ignore = array('.svn', '.git'))
    {
        $array_ignore = array_merge(array('.', '..'), $ignore);
        $content = scandir($dir);
        foreach ($content as $filename) {
            if (!in_array($filename, $array_ignore)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Delete directory and subdirectories
     *
     * @param string $dirname Directory name
     */
    public static function deleteDirectory($dirname, $delete_self = true)
    {
        return Tools14::deleteDirectory($dirname, $delete_self);
    }
    /**
     * try to restore db backup file
     */
    public function ajaxProcessRestoreDb()
    {
        $skip_ignore_tables = false;
        $ignore_stats_table = array(
            _DB_PREFIX_.'connections',
            _DB_PREFIX_.'connections_page',
            _DB_PREFIX_.'connections_source',
            _DB_PREFIX_.'guest',
            _DB_PREFIX_.'statssearch'
        );
        $this->nextParams['dbStep'] = $this->currentParams['dbStep'];
        $start_time = time();
        $db = $this->db;
        $listQuery = array();
        $errors = array();

        // deal with running backup rest if exist
        if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList)) {
            $listQuery = unserialize(base64_decode(file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList)));
        }

        // deal with the next files stored in restoreDbFilenames
        if (empty($listQuery) && is_array($this->restoreDbFilenames) && count($this->restoreDbFilenames) > 0) {
            $currentDbFilename = array_shift($this->restoreDbFilenames);
            if (!preg_match('#auto-backupdb_([0-9]{6})_#', $currentDbFilename, $match)) {
                $this->next = 'error';
                $this->error = 1;
                $this->nextQuickInfo[] = $this->next_desc = $this->trans('%s: File format does not match.', array($currentDbFilename), 'Modules.Autoupgrade.Admin');
                return false;
            }
            $this->nextParams['dbStep'] = $match[1];
            $backupdb_path = $this->backupPath.DIRECTORY_SEPARATOR.$this->restoreName;

            $dot_pos = strrpos($currentDbFilename, '.');
            $fileext = substr($currentDbFilename, $dot_pos+1);
            $requests = array();
            $content = '';

            $this->nextQuickInfo[] = $this->trans(
                'Opening backup database file %filename% in %extension% mode',
                array(
                    '%filename%' => $currentDbFilename,
                    '%extension%' => $fileext,
                ),
                'Modules.Autoupgrade.Admin'
            );

            switch ($fileext) {
                case 'bz':
                case 'bz2':
                    if ($fp = bzopen($backupdb_path.DIRECTORY_SEPARATOR.$currentDbFilename, 'r')) {
                        while (!feof($fp)) {
                            $content .= bzread($fp, 4096);
                        }
                    } else {
                        die("error when trying to open in bzmode");
                    } // @todo : handle error
                    break;
                case 'gz':
                    if ($fp = gzopen($backupdb_path.DIRECTORY_SEPARATOR.$currentDbFilename, 'r')) {
                        while (!feof($fp)) {
                            $content .= gzread($fp, 4096);
                        }
                    }
                    gzclose($fp);
                    break;
                default:
                    if ($fp = fopen($backupdb_path.DIRECTORY_SEPARATOR.$currentDbFilename, 'r')) {
                        while (!feof($fp)) {
                            $content .= fread($fp, 4096);
                        }
                    }
                    fclose($fp);
            }
            $currentDbFilename = '';

            if (empty($content)) {
                $this->nextErrors[] = $this->trans('Database backup is empty.', array(), 'Modules.Autoupgrade.Admin');
                $this->nextQuickInfo[] = $this->trans('Database backup is empty.', array(), 'Modules.Autoupgrade.Admin');
                $this->next = 'rollback';
                return false;
            }

            // preg_match_all is better than preg_split (what is used in do Upgrade.php)
            // This way we avoid extra blank lines
            // option s (PCRE_DOTALL) added
            $listQuery = preg_split('/;[\n\r]+/Usm', $content);
            unset($content);

            // Get tables before backup
            if ($this->nextParams['dbStep'] == '1') {
                $tables_after_restore = array();
                foreach ($listQuery as $q) {
                    if (preg_match('/`(?<table>'._DB_PREFIX_.'[a-zA-Z0-9_-]+)`/', $q, $matches)) {
                        if (isset($matches['table'])) {
                            $tables_after_restore[$matches['table']] = $matches['table'];
                        }
                    }
                }

                $tables_after_restore = array_unique($tables_after_restore);
                $tables_before_restore = $this->databaseTools->getAllTables();
                $tablesToRemove = array_diff($tables_before_restore, $tables_after_restore);

                if (!empty($tablesToRemove)) {
                    file_put_contents($this->autoupgradePath . DIRECTORY_SEPARATOR . $this->toCleanTable, base64_encode(serialize($tablesToRemove)));
                }
            }
        }

        // @todo : error if listQuery is not an array (that can happen if toRestoreQueryList is empty for example)
        $time_elapsed = time() - $start_time;
        if (is_array($listQuery) && (count($listQuery) > 0)) {
            $this->db->execute('SET SESSION sql_mode = \'\'');
            $this->db->execute('SET FOREIGN_KEY_CHECKS=0');

            do {
                if (count($listQuery) == 0) {
                    if (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList)) {
                        unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList);
                    }

                    if (count($this->restoreDbFilenames)) {
                        $this->next_desc = $this->trans(
                            'Database restoration file %filename% done. %filescount% file(s) left...',
                            array(
                                '%filename%' => $this->nextParams['dbStep'],
                                '%filescount%' => count($this->restoreDbFilenames),
                            ),
                            'Modules.Autoupgrade.Admin'
                        );
                    } else {
                        $this->next_desc = $this->trans('Database restoration file %1$s done.', array($this->nextParams['dbStep']), 'Modules.Autoupgrade.Admin');
                    }

                    $this->nextQuickInfo[] = $this->next_desc;
                    $this->stepDone = true;
                    $this->status = 'ok';
                    $this->next = 'restoreDb';

                    if (count($this->restoreDbFilenames) == 0) {
                        $this->next = 'rollbackComplete';
                        $this->nextQuickInfo[] = $this->next_desc = $this->trans('Database has been restored.', array(), 'Modules.Autoupgrade.Admin');

                        $this->databaseTools->cleanTablesAfterBackup($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toCleanTable);
                        $this->db->execute('SET FOREIGN_KEY_CHECKS=1');
                    }
                    return true;
                }

                // filesForBackup already contains all the correct files
                if (count($listQuery) == 0) {
                    continue;
                }

                $query = trim(array_shift($listQuery));
                if (!empty($query)) {
                    if (!$this->db->execute($query, false)) {
                        if (is_array($listQuery)) {
                            $listQuery = array_unshift($listQuery, $query);
                        }
                        $this->nextErrors[] = $this->trans('[SQL ERROR]', array(), 'Modules.Autoupgrade.Admin').' '.$query.' - '.$this->db->getMsgError();
                        $this->nextQuickInfo[] = $this->trans('[SQL ERROR]', array(), 'Modules.Autoupgrade.Admin').' '.$query.' - '.$this->db->getMsgError();
                        $this->next = 'error';
                        $this->error = 1;
                        $this->next_desc = $this->trans('Error during database restoration', array(), 'Modules.Autoupgrade.Admin');
                        unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList);
                        return false;
                    }
                }

                // note : theses queries can be too big and can cause issues for display
                // else
                // $this->nextQuickInfo[] = '[OK] '.$query;

                $time_elapsed = time() - $start_time;
            } while ($time_elapsed < self::$loopRestoreQueryTime);

            $queries_left = count($listQuery);

            if ($queries_left > 0) {
                file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList, base64_encode(serialize($listQuery)));
            } elseif (file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList)) {
                unlink($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toRestoreQueryList);
            }

            $this->stepDone = false;
            $this->next = 'restoreDb';
            $this->nextQuickInfo[] = $this->next_desc = $this->trans(
                '%numberqueries% queries left for file %filename%...',
                array(
                    '%numberqueries%' => $queries_left,
                    '%filename%' => $this->nextParams['dbStep'],
                ),
                'Modules.Autoupgrade.Admin'
            );
            unset($query);
            unset($listQuery);
        } else {
            $this->stepDone = true;
            $this->status = 'ok';
            $this->next = 'rollbackComplete';
            $this->nextQuickInfo[] = $this->next_desc = $this->trans('Database restoration done.', array(), 'Modules.Autoupgrade.Admin');

            $this->databaseTools->cleanTablesAfterBackup($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toCleanTable);
            $this->db->execute('SET FOREIGN_KEY_CHECKS=1');
        }
        return true;
    }

    public function ajaxProcessMergeTranslations()
    {
    }

    public function ajaxProcessBackupDb()
    {
        if (!$this->upgradeConfiguration->get('PS_AUTOUP_BACKUP')) {
            $this->stepDone = true;
            $this->nextParams['dbStep'] = 0;
            $this->next_desc = $this->trans('Database backup skipped. Now upgrading files...', array(), 'Modules.Autoupgrade.Admin');
            $this->next = 'upgradeFiles';
            return true;
        }

        $relative_backup_path = str_replace(_PS_ROOT_DIR_, '', $this->backupPath);
        $report = '';
        if (!ConfigurationTest::test_dir($relative_backup_path, false, $report)) {
            $this->next_desc = $this->trans('Backup directory is not writable (%path%).', array('%path%' => $this->backupPath), 'Modules.Autoupgrade.Admin');
            $this->nextQuickInfo[] = $this->trans('Backup directory is not writable (%path%).', array('%path%' => $this->backupPath), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('Backup directory is not writable (%path%).', array('%path%' => $this->backupPath), 'Modules.Autoupgrade.Admin');
            $this->next = 'error';
            $this->error = 1;
            return false;
        }

        $this->stepDone = false;
        $this->next = 'backupDb';
        $this->nextParams = $this->currentParams;
        $start_time = time();

        $psBackupAll = true;
        $psBackupDropTable = true;
        if (!$psBackupAll) {
            $ignore_stats_table = array(_DB_PREFIX_.'connections',
                                                        _DB_PREFIX_.'connections_page',
                                                        _DB_PREFIX_.'connections_source',
                                                        _DB_PREFIX_.'guest',
                                                        _DB_PREFIX_.'statssearch');
        } else {
            $ignore_stats_table = array();
        }

        // INIT LOOP
        if (!file_exists($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupDbList)) {
            if (!is_dir($this->backupPath.DIRECTORY_SEPARATOR.$this->backupName)) {
                mkdir($this->backupPath.DIRECTORY_SEPARATOR.$this->backupName);
            }
            $this->nextParams['dbStep'] = 0;
            $tablesToBackup = $this->db->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'%"', true, false);
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupDbList, base64_encode(serialize($tablesToBackup)));
        }

        if (!isset($tablesToBackup)) {
            $tablesToBackup = unserialize(base64_decode(file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupDbList)));
        }
        $found = 0;
        $views = '';

        // MAIN BACKUP LOOP //
        $written = 0;
        do {
            if (!empty($this->nextParams['backup_table'])) {
                // only insert (schema already done)
                $table = $this->nextParams['backup_table'];
                $lines = $this->nextParams['backup_lines'];
            } else {
                if (count($tablesToBackup) == 0) {
                    break;
                }
                $table = current(array_shift($tablesToBackup));
                $this->nextParams['backup_loop_limit'] = 0;
            }

            if ($written == 0 || $written > self::$max_written_allowed) {
                // increment dbStep will increment filename each time here
                $this->nextParams['dbStep']++;
                // new file, new step
                $written = 0;
                if (isset($fp)) {
                    fclose($fp);
                }
                $backupfile = $this->backupPath.DIRECTORY_SEPARATOR.$this->backupName.DIRECTORY_SEPARATOR.$this->backupDbFilename;
                $backupfile = preg_replace("#_XXXXXX_#", '_'.str_pad($this->nextParams['dbStep'], 6, '0', STR_PAD_LEFT).'_', $backupfile);

                // start init file
                // Figure out what compression is available and open the file
                if (file_exists($backupfile)) {
                    $this->next = 'error';
                    $this->error = 1;
                    $this->nextErrors[] = $this->trans('Backup file %s already exists. Operation aborted.', array($backupfile), 'Modules.Autoupgrade.Admin');
                    $this->nextQuickInfo[] = $this->trans('Backup file %s already exists. Operation aborted.', array($backupfile), 'Modules.Autoupgrade.Admin');
                }

                if (function_exists('bzopen')) {
                    $backupfile .= '.bz2';
                    $fp = bzopen($backupfile, 'w');
                } elseif (function_exists('gzopen')) {
                    $backupfile .= '.gz';
                    $fp = gzopen($backupfile, 'w');
                } else {
                    $fp = fopen($backupfile, 'w');
                }

                if ($fp === false) {
                    $this->nextErrors[] = $this->trans('Unable to create backup database file %s.', array(addslashes($backupfile)), 'Modules.Autoupgrade.Admin');
                    $this->nextQuickInfo[] = $this->trans('Unable to create backup database file %s.', array(addslashes($backupfile)), 'Modules.Autoupgrade.Admin');
                    $this->next = 'error';
                    $this->error = 1;
                    $this->next_desc = $this->trans('Error during database backup.', array(), 'Modules.Autoupgrade.Admin');
                    return false;
                }

                $written += fwrite($fp, '/* Backup ' . $this->nextParams['dbStep'] . ' for ' . Tools14::getHttpHost(false, false) . __PS_BASE_URI__ . "\n *  at " . date('r') . "\n */\n");
                $written += fwrite($fp, "\n".'SET SESSION sql_mode = \'\';'."\n\n");
                $written += fwrite($fp, "\n".'SET NAMES \'utf8\';'."\n\n");
                $written += fwrite($fp, "\n".'SET FOREIGN_KEY_CHECKS=0;'."\n\n");
                // end init file
            }


            // Skip tables which do not start with _DB_PREFIX_
            if (strlen($table) <= strlen(_DB_PREFIX_) || strncmp($table, _DB_PREFIX_, strlen(_DB_PREFIX_)) != 0) {
                continue;
            }

            // start schema : drop & create table only
            if (empty($this->currentParams['backup_table'])) {
                // Export the table schema
                $schema = $this->db->executeS('SHOW CREATE TABLE `' . $table . '`', true, false);

                if (count($schema) != 1 ||
                    !((isset($schema[0]['Table']) && isset($schema[0]['Create Table']))
                        || (isset($schema[0]['View']) && isset($schema[0]['Create View'])))) {
                    fclose($fp);
                    if (file_exists($backupfile)) {
                        unlink($backupfile);
                    }
                    $this->nextErrors[] = $this->trans('An error occurred while backing up. Unable to obtain the schema of %s', array($table), 'Modules.Autoupgrade.Admin');
                    $this->nextQuickInfo[] = $this->trans('An error occurred while backing up. Unable to obtain the schema of %s', array($table), 'Modules.Autoupgrade.Admin');
                    $this->next = 'error';
                    $this->error = 1;
                    $this->next_desc = $this->trans('Error during database backup.', array(), 'Modules.Autoupgrade.Admin');
                    return false;
                }

                // case view
                if (isset($schema[0]['View'])) {
                    $views .= '/* Scheme for view' . $schema[0]['View'] . " */\n";
                    if ($psBackupDropTable) {
                        // If some *upgrade* transform a table in a view, drop both just in case
                        $views .= 'DROP VIEW IF EXISTS `'.$schema[0]['View'].'`;'."\n";
                        $views .= 'DROP TABLE IF EXISTS `'.$schema[0]['View'].'`;'."\n";
                    }
                    $views .= preg_replace('#DEFINER=[^\s]+\s#', 'DEFINER=CURRENT_USER ', $schema[0]['Create View']).";\n\n";
                    $written += fwrite($fp, "\n".$views);
                    $ignore_stats_table[] = $schema[0]['View'];
                }
                // case table
                elseif (isset($schema[0]['Table'])) {
                    // Case common table
                    $written += fwrite($fp, '/* Scheme for table ' . $schema[0]['Table'] . " */\n");
                    if ($psBackupDropTable && !in_array($schema[0]['Table'], $ignore_stats_table)) {
                        // If some *upgrade* transform a table in a view, drop both just in case
                        $written += fwrite($fp, 'DROP VIEW IF EXISTS `'.$schema[0]['Table'].'`;'."\n");
                        $written += fwrite($fp, 'DROP TABLE IF EXISTS `'.$schema[0]['Table'].'`;'."\n");
                        // CREATE TABLE
                        $written += fwrite($fp, $schema[0]['Create Table'] . ";\n\n");
                    }
                    // schema created, now we need to create the missing vars
                    $this->nextParams['backup_table'] = $table;
                    $lines = $this->nextParams['backup_lines'] = explode("\n", $schema[0]['Create Table']);
                }
            }
            // end of schema

            // POPULATE TABLE
            if (!in_array($table, $ignore_stats_table)) {
                do {
                    $backup_loop_limit = $this->nextParams['backup_loop_limit'];
                    $data = $this->db->executeS('SELECT * FROM `'.$table.'` LIMIT '.(int)$backup_loop_limit.',200', false, false);
                    $this->nextParams['backup_loop_limit'] += 200;
                    $sizeof = $this->db->numRows();
                    if ($data && ($sizeof > 0)) {
                        // Export the table data
                        $written += fwrite($fp, 'INSERT INTO `'.$table."` VALUES\n");
                        $i = 1;
                        while ($row = $this->db->nextRow($data)) {
                            // this starts a row
                            $s = '(';
                            foreach ($row as $field => $value) {
                                $tmp = "'" . $this->db->escape($value, true) . "',";
                                if ($tmp != "'',") {
                                    $s .= $tmp;
                                } else {
                                    foreach ($lines as $line) {
                                        if (strpos($line, '`'.$field.'`') !== false) {
                                            if (preg_match('/(.*NOT NULL.*)/Ui', $line)) {
                                                $s .= "'',";
                                            } else {
                                                $s .= 'NULL,';
                                            }
                                            break;
                                        }
                                    }
                                }
                            }
                            $s = rtrim($s, ',');

                            if ($i < $sizeof) {
                                $s .= "),\n";
                            } else {
                                $s .= ");\n";
                            }

                            $written += fwrite($fp, $s);
                            ++$i;
                        }
                        $time_elapsed = time() - $start_time;
                    } else {
                        unset($this->nextParams['backup_table']);
                        unset($this->currentParams['backup_table']);
                        break;
                    }
                } while (($time_elapsed < self::$loopBackupDbTime) && ($written < self::$max_written_allowed));
            }
            $found++;
            $time_elapsed = time() - $start_time;
            $this->nextQuickInfo[] = $this->trans('%s table has been saved.', array($table), 'Modules.Autoupgrade.Admin');
        } while (($time_elapsed < self::$loopBackupDbTime) && ($written < self::$max_written_allowed));

        // end of loop
        if (isset($fp)) {
            $written += fwrite($fp, "\n".'SET FOREIGN_KEY_CHECKS=1;'."\n\n");
            fclose($fp);
            unset($fp);
        }

        file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupDbList, base64_encode(serialize($tablesToBackup)));

        if (count($tablesToBackup) > 0) {
            $this->nextQuickInfo[] = $this->trans('%s tables have been saved.', array($found), 'Modules.Autoupgrade.Admin');
            $this->next = 'backupDb';
            $this->stepDone = false;
            if (count($tablesToBackup)) {
                $this->next_desc = $this->trans('Database backup: %s table(s) left...', array(count($tablesToBackup)), 'Modules.Autoupgrade.Admin');
                $this->nextQuickInfo[] = $this->trans('Database backup: %s table(s) left...', array(count($tablesToBackup)), 'Modules.Autoupgrade.Admin');
            }
            return true;
        }
        if ($found == 0 && !empty($backupfile)) {
            if (file_exists($backupfile)) {
                unlink($backupfile);
            }
            $this->nextErrors[] = $this->trans('No valid tables were found to back up. Backup of file %s canceled.', array($backupfile), 'Modules.Autoupgrade.Admin');
            $this->nextQuickInfo[] = $this->trans('No valid tables were found to back up. Backup of file %s canceled.', array($backupfile), 'Modules.Autoupgrade.Admin');
            $this->error = 1;
            $this->next_desc = $this->trans('Error during database backup for file %s.', array($backupfile), 'Modules.Autoupgrade.Admin');
            return false;
        } else {
            unset($this->nextParams['backup_loop_limit']);
            unset($this->nextParams['backup_lines']);
            unset($this->nextParams['backup_table']);
            if ($found) {
                $this->nextQuickInfo[] = $this->trans('%s tables have been saved.', array($found), 'Modules.Autoupgrade.Admin');
            }
            $this->stepDone = true;
            // reset dbStep at the end of this step
            $this->nextParams['dbStep'] = 0;

            $this->next_desc = $this->trans('Database backup done in filename %s. Now upgrading files...', array($this->backupName), 'Modules.Autoupgrade.Admin');
            $this->next = 'upgradeFiles';
            return true;
        }
    }

    public function ajaxProcessBackupFiles()
    {
        if (!$this->upgradeConfiguration->get('PS_AUTOUP_BACKUP')) {
            $this->stepDone = true;
            $this->next = 'backupDb';
            $this->next_desc = 'File backup skipped.';
            return true;
        }

        $this->nextParams = $this->currentParams;
        $this->stepDone = false;
        if (empty($this->backupFilesFilename)) {
            $this->next = 'error';
            $this->error = 1;
            $this->next_desc = $this->trans('Error during backupFiles', array(), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('[ERROR] backupFiles filename has not been set', array(), 'Modules.Autoupgrade.Admin');
            $this->nextQuickInfo[] = $this->trans('[ERROR] backupFiles filename has not been set', array(), 'Modules.Autoupgrade.Admin');
            return false;
        }

        if (empty($this->nextParams['filesForBackup'])) {
            // @todo : only add files and dir listed in "originalPrestashopVersion" list
            $filesToBackup = $this->_listFilesInDir($this->prodRootDir, 'backup', false);
            file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupFileList, base64_encode(serialize($filesToBackup)));
            if (count($this->toBackupFileList)) {
                $this->nextQuickInfo[] = $this->trans('%s Files to backup.', array(count($this->toBackupFileList)), 'Modules.Autoupgrade.Admin');
            }
            $this->nextParams['filesForBackup'] = $this->toBackupFileList;

            // delete old backup, create new
            if (!empty($this->backupFilesFilename) && file_exists($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename)) {
                unlink($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename);
            }

            $this->nextQuickInfo[]    = $this->trans('Backup files initialized in %s', array($this->backupFilesFilename), 'Modules.Autoupgrade.Admin');
        }
        $filesToBackup = unserialize(base64_decode(file_get_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupFileList)));

        $this->next = 'backupFiles';
        if (count($this->toBackupFileList)) {
            $this->next_desc = $this->trans('Backup files in progress. %d files left', array(count($filesToBackup)), 'Modules.Autoupgrade.Admin');
        }
        if (is_array($filesToBackup)) {
            $res = false;
            if (!self::$force_pclZip && class_exists('ZipArchive', false)) {
                $this->nextQuickInfo[] = $this->trans('Using class ZipArchive...', array(), 'Modules.Autoupgrade.Admin');
                $zip_archive = true;
                $zip = new ZipArchive();
                $res = $zip->open($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename, ZIPARCHIVE::CREATE);
                if ($res) {
                    $res = (isset($zip->filename) && $zip->filename) ? true : false;
                }
            }

            if (!$res) {
                $zip_archive = false;
                $this->nextQuickInfo[] = $this->trans('Using class PclZip...', array(), 'Modules.Autoupgrade.Admin');
                // pclzip can be already loaded (server configuration)
                if (!class_exists('PclZip', false)) {
                    require_once(dirname(__FILE__).'/classes/pclzip.lib.php');
                }
                $zip = new PclZip($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename);
                $res = true;
            }

            if ($zip && $res) {
                $this->next = 'backupFiles';
                $this->stepDone = false;
                $files_to_add = array();
                $close_flag = true;
                for ($i = 0; $i < self::$loopBackupFiles; $i++) {
                    if (count($filesToBackup) <= 0) {
                        $this->stepDone = true;
                        $this->status = 'ok';
                        $this->next = 'backupDb';
                        $this->next_desc = $this->trans('All files saved. Now backing up database', array(), 'Modules.Autoupgrade.Admin');
                        $this->nextQuickInfo[] = $this->trans('All files have been added to archive.', array(), 'Modules.Autoupgrade.Admin');
                        break;
                    }
                    // filesForBackup already contains all the correct files
                    $file = array_shift($filesToBackup);

                    $archiveFilename = ltrim(str_replace($this->prodRootDir, '', $file), DIRECTORY_SEPARATOR);
                    $size = filesize($file);
                    if ($size < self::$maxBackupFileSize) {
                        if ($zip_archive) {
                            $added_to_zip = $zip->addFile($file, $archiveFilename);
                            if ($added_to_zip) {
                                if ($filesToBackup) {
                                    $this->nextQuickInfo[] = $this->trans(
                                        '%filename% added to archive. %filescount% files left.',
                                        array(
                                            '%filename%' => $archiveFilename,
                                            '%filescount%' => count($filesToBackup),
                                        ),
                                        'Modules.Autoupgrade.Admin'
                                    );
                                }
                            } else {
                                // if an error occur, it's more safe to delete the corrupted backup
                                $zip->close();
                                if (file_exists($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename)) {
                                    unlink($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename);
                                }
                                $this->next = 'error';
                                $this->error = 1;
                                $this->next_desc = $this->trans(
                                    'Error when trying to add %filename% to archive %archive%.',
                                    array(
                                        '%filename%' => $file,
                                        '%archive%' => $archiveFilename,
                                    ),
                                    'Modules.Autoupgrade.Admin'
                                );
                                $close_flag = false;
                                break;
                            }
                        } else {
                            $files_to_add[] = $file;
                            if (count($filesToBackup)) {
                                $this->nextQuickInfo[] = $this->trans(
                                    'File %filename% (size: %filesize%) added to archive. %filescount% files left.',
                                    array(
                                        '%filename%' => $archiveFilename,
                                        '%filesize%' => $size,
                                        '%filescount%' => count($filesToBackup),
                                    ),
                                    'Modules.Autoupgrade.Admin'
                                );
                            } else {
                                $this->nextQuickInfo[] = $this->trans(
                                    'File %filename% (size: %filesize%) added to archive.',
                                    array(
                                        '%filename%' => $archiveFilename,
                                        '%filesize%' => $size,
                                    ),
                                    'Modules.Autoupgrade.Admin'
                                );
                            }
                        }
                    } else {
                        $this->nextQuickInfo[] = $this->trans(
                            'File %filename% (size: %filesize%) has been skipped during backup.',
                            array(
                                '%filename%' => $archiveFilename,
                                '%filesize%' => $size,
                            ),
                            'Modules.Autoupgrade.Admin'
                        );
                        $this->nextErrors[] = $this->trans(
                            'File %filename% (size: %filesize%) has been skipped during backup.',
                            array(
                                '%filename%' => $archiveFilename,
                                '%filesize%' => $size,
                            ),
                            'Modules.Autoupgrade.Admin'
                        );
                    }
                }

                if ($zip_archive && $close_flag && is_object($zip)) {
                    $zip->close();
                } elseif (!$zip_archive) {
                    $added_to_zip = $zip->add($files_to_add, PCLZIP_OPT_REMOVE_PATH, $this->prodRootDir);
                    if ($added_to_zip) {
                        $zip->privCloseFd();
                    }
                    if (!$added_to_zip) {
                        if (file_exists($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename)) {
                            unlink($this->backupPath.DIRECTORY_SEPARATOR.$this->backupFilesFilename);
                        }
                        $this->nextQuickInfo[] = $this->trans('[ERROR] Error on backup using PclZip: %s.', array($zip->errorInfo(true)), 'Modules.Autoupgrade.Admin');
                        $this->nextErrors[] = $this->trans('[ERROR] Error on backup using PclZip: %s.', array($zip->errorInfo(true)), 'Modules.Autoupgrade.Admin');
                        $this->next = 'error';
                    }
                }

                file_put_contents($this->autoupgradePath.DIRECTORY_SEPARATOR.$this->toBackupFileList, base64_encode(serialize($filesToBackup)));
                return true;
            } else {
                $this->next = 'error';
                $this->next_desc = $this->trans('Unable to open archive', array(), 'Modules.Autoupgrade.Admin');
                return false;
            }
        } else {
            $this->stepDone = true;
            $this->next = 'backupDb';
            $this->next_desc = $this->trans('All files saved. Now backing up database.', array(), 'Modules.Autoupgrade.Admin');
            return true;
        }
        // 4) save for display.
    }


    private function _removeOneSample($removeList)
    {
        if (is_array($removeList) and count($removeList) > 0) {
            if (file_exists($removeList[0]) and unlink($removeList[0])) {
                $item = str_replace($this->prodRootDir, '', array_shift($removeList));
                $this->next = 'removeSamples';
                $this->nextParams['removeList'] = $removeList;
                if (count($removeList) > 0) {
                    $this->nextQuickInfo[] = $this->trans(
                        '%itemname% items removed. %itemscount% items left.',
                        array(
                            '%itemname%' => $item,
                            '%itemscount%' => count($removeList)
                        ),
                        'Modules.Autoupgrade.Admin'
                    );
                }
            } else {
                $this->next = 'error';
                $this->nextParams['removeList'] = $removeList;
                $this->nextQuickInfo[] = $this->trans(
                    'Error while removing item %itemname%, %itemscount% items left.',
                    array(
                        '%itemname%' => $removeList[0],
                        '%itemscount%' => count($removeList)
                    ),
                    'Modules.Autoupgrade.Admin'
                );
                $this->nextErrors[] = $this->trans(
                    'Error while removing item %itemname%, %itemscount% items left.',
                    array(
                        '%itemname%' => $removeList[0],
                        '%itemscount%' => count($removeList)
                    ),
                    'Modules.Autoupgrade.Admin'
                );
                return false;
            }
        }
        return true;
    }

    /**
     * Remove all sample files.
     *
     * @return boolean true if succeed
     */
    public function ajaxProcessRemoveSamples()
    {
        $this->stepDone = false;
        // remove all sample pics in img subdir
        if (!isset($this->currentParams['removeList'])) {
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/c', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/cms', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/l', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/m', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/os', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/p', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/s', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/scenes', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/st', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img/su', '.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img', '404.gif');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img', 'favicon.ico');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img', 'logo.jpg');
            $this->_listSampleFiles($this->latestPath.'/prestashop/img', 'logo_stores.gif');
            $this->_listSampleFiles($this->latestPath.'/prestashop/modules/editorial', 'homepage_logo.jpg');
            // remove all override present in the archive
            $this->_listSampleFiles($this->latestPath.'/prestashop/override', '.php');

            if (count($this->sampleFileList) > 0) {
                $this->nextQuickInfo[] = $this->trans('Starting to remove %s sample files', array(count($this->sampleFileList)), 'Modules.Autoupgrade.Admin');
            }
            $this->nextParams['removeList'] = $this->sampleFileList;
        }

        $resRemove = true;
        for ($i = 0; $i < self::$loopRemoveSamples; $i++) {
            if (count($this->nextParams['removeList']) <= 0) {
                $this->stepDone = true;
                if ($this->upgradeConfiguration->get('skip_backup')) {
                    $this->next = 'upgradeFiles';
                    $this->next_desc = $this->trans('All sample files removed. Backup process skipped. Now upgrading files.', array(), 'Modules.Autoupgrade.Admin');
                } else {
                    $this->next = 'backupFiles';
                    $this->next_desc = $this->trans('All sample files removed. Now backing up files.', array(), 'Modules.Autoupgrade.Admin');
                }
                // break the loop, all sample already removed
                return true;
            }
            $resRemove &= $this->_removeOneSample($this->nextParams['removeList']);
            if (!$resRemove) {
                break;
            }
        }

        return $resRemove;
    }

    /**
     * download PrestaShop archive according to the chosen channel
     *
     * @access public
     */
    public function ajaxProcessDownload()
    {
        if (ConfigurationTest::test_fopen() || ConfigurationTest::test_curl()) {
            if (!is_object($this->upgrader)) {
                $this->upgrader = new Upgrader();
            }
            // regex optimization
            preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
            $this->upgrader->channel = $this->upgradeConfiguration->get('channel');
            $this->upgrader->branch = $matches[1];
            if ($this->upgradeConfiguration->get('channel') == 'private' && !$this->upgradeConfiguration->get('private_allow_major')) {
                $this->upgrader->checkPSVersion(false, array('private', 'minor'));
            } else {
                $this->upgrader->checkPSVersion(false, array('minor'));
            }

            if ($this->upgrader->channel == 'private') {
                $this->upgrader->link = $this->upgradeConfiguration->get('private_release_link');
                $this->upgrader->md5 = $this->upgradeConfiguration->get('private_release_md5');
            }
            $this->nextQuickInfo[] = $this->trans('Downloading from %s', array($this->upgrader->link), 'Modules.Autoupgrade.Admin');
            $this->nextQuickInfo[] = $this->trans('File will be saved in %s', array($this->getFilePath()), 'Modules.Autoupgrade.Admin');
            if (file_exists($this->downloadPath)) {
                self::deleteDirectory($this->downloadPath, false);
                $this->nextQuickInfo[] = $this->trans('Download directory has been emptied', array(), 'Modules.Autoupgrade.Admin');
            }
            $report = '';
            $relative_download_path = str_replace(_PS_ROOT_DIR_, '', $this->downloadPath);
            if (ConfigurationTest::test_dir($relative_download_path, false, $report)) {
                $res = $this->upgrader->downloadLast($this->downloadPath, $this->destDownloadFilename);
                if ($res) {
                    $md5file = md5_file(realpath($this->downloadPath).DIRECTORY_SEPARATOR.$this->destDownloadFilename);
                    if ($md5file == $this->upgrader->md5) {
                        $this->nextQuickInfo[] = $this->trans('Download complete.', array(), 'Modules.Autoupgrade.Admin');
                        $this->next = 'unzip';
                        $this->next_desc = $this->trans('Download complete. Now extracting...', array(), 'Modules.Autoupgrade.Admin');
                    } else {
                        $this->nextQuickInfo[] = $this->trans('Download complete but MD5 sum does not match (%s).', array($md5file), 'Modules.Autoupgrade.Admin');
                        $this->nextErrors[] = $this->trans('Download complete but MD5 sum does not match (%s).', array($md5file), 'Modules.Autoupgrade.Admin');
                        $this->next = 'error';
                        $this->next_desc = $this->trans('Download complete but MD5 sum does not match (%s). Operation aborted.', array(), 'Modules.Autoupgrade.Admin');
                    }
                } else {
                    if ($this->upgrader->channel == 'private') {
                        $this->next_desc = $this->trans('Error during download. The private key may be incorrect.', array(), 'Modules.Autoupgrade.Admin');
                        $this->nextQuickInfo[] = $this->trans('Error during download. The private key may be incorrect.', array(), 'Modules.Autoupgrade.Admin');
                        $this->nextErrors[] = $this->trans('Error during download. The private key may be incorrect.', array(), 'Modules.Autoupgrade.Admin');
                    } else {
                        $this->next_desc = $this->trans('Error during download', array(), 'Modules.Autoupgrade.Admin');
                        $this->nextQuickInfo[] = $this->trans('Error during download', array(), 'Modules.Autoupgrade.Admin');
                        $this->nextErrors[] = $this->trans('Error during download', array(), 'Modules.Autoupgrade.Admin');
                    }
                    $this->next = 'error';
                }
            } else {
                $this->next_desc = $this->trans('Download directory %s is not writable.', array($this->downloadPath), 'Modules.Autoupgrade.Admin');
                $this->nextQuickInfo[] = $this->trans('Download directory %s is not writable.', array($this->downloadPath), 'Modules.Autoupgrade.Admin');
                $this->nextErrors[] = $this->trans('Download directory %s is not writable.', array($this->downloadPath), 'Modules.Autoupgrade.Admin');
                $this->next = 'error';
            }
        } else {
            $this->nextQuickInfo[] = $this->trans('You need allow_url_fopen or cURL enabled for automatic download to work. You can also manually upload it in filepath %s.', array($this->getFilePath()), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('You need allow_url_fopen or cURL enabled for automatic download to work. You can also manually upload it in filepath %s.', array($this->getFilePath()), 'Modules.Autoupgrade.Admin');
            $this->next = 'error';
            $this->next_desc = $this->trans('You need allow_url_fopen or cURL enabled for automatic download to work. You can also manually upload it in filepath %s.', array($this->getFilePath()), 'Modules.Autoupgrade.Admin');
        }
    }

    public function buildAjaxResult()
    {
        $return = array();

        $return['error'] = $this->error;
        $return['stepDone'] = $this->stepDone;
        $return['next'] = $this->next;
        $return['status'] = $this->next == 'error' ? 'error' : 'ok';
        $return['next_desc'] = $this->next_desc;

        $this->nextParams['config'] = $this->upgradeConfiguration->toArray();

        foreach ($this->ajaxParams as $v) {
            if (property_exists($this, $v)) {
                $this->nextParams[$v] = $this->$v;
            } else {
                $this->nextQuickInfo[] = $this->trans('[WARNING] Property %s is missing', array($v), 'Modules.Autoupgrade.Admin');
            }
        }

        $return['nextParams'] = $this->nextParams;
        if (!isset($return['nextParams']['dbStep'])) {
            $return['nextParams']['dbStep'] = 0;
        }

        $return['nextParams']['typeResult'] = $this->nextResponseType;

        $return['nextQuickInfo'] = $this->nextQuickInfo;
        $return['nextErrors'] = $this->nextErrors;
        return Tools14::jsonEncode($return);
    }

    public function ajaxPreProcess()
    {
        /* PrestaShop demo mode */
        if (defined('_PS_MODE_DEMO_') && _PS_MODE_DEMO_) {
            return;
        }

        /* PrestaShop demo mode*/
        if (!empty($_POST['responseType']) && $_POST['responseType'] == 'json') {
            header('Content-Type: application/json');
        }

        if (!empty($_POST['action'])) {
            $action = $_POST['action'];
            if (isset(self::$skipAction[$action])) {
                $this->next = self::$skipAction[$action];
                $this->nextQuickInfo[] = $this->next_desc = $this->trans('Action %s skipped', array($action), 'Modules.Autoupgrade.Admin');
                unset($_POST['action']);
            } elseif (!method_exists(get_class($this), 'ajaxProcess'.$action)) {
                $this->next_desc = $this->trans('Action "%s" not found', array($action), 'Modules.Autoupgrade.Admin');
                $this->next = 'error';
                $this->error = '1';
            }
        }

        if (!method_exists('Tools14', 'apacheModExists') || Tools14::apacheModExists('evasive')) {
            sleep(1);
        }
    }

    public function displayAjax()
    {
        echo $this->buildAjaxResult();
    }

    protected function getBackupFilesAvailable()
    {
        $array = array();
        $files = scandir($this->backupPath);
        foreach ($files as $file) {
            if ($file[0] != '.') {
                if (substr($file, 0, 16) == 'auto-backupfiles') {
                    $array[] = preg_replace('#^auto-backupfiles_(.*-[0-9a-f]{1,8})\..*$#', '$1', $file);
                }
            }
        }

        return $array;
    }

    protected function getBackupDbAvailable()
    {
        $array = array();

        $files = scandir($this->backupPath);

        foreach ($files as $file) {
            if ($file[0] == 'V' && is_dir($this->backupPath.DIRECTORY_SEPARATOR.$file)) {
                $array[] = $file;
            }
        }
        return $array;
    }

    public function divChannelInfos($upgrade_info)
    {
        if ($this->upgradeConfiguration->get('channel') == 'private') {
            $upgrade_info['link'] = $this->upgradeConfiguration->get('private_release_link');
            $upgrade_info['md5'] = $this->upgradeConfiguration->get('private_release_md5');
        }
        $content = '<div id="channel-infos" ><br/>';
        if (isset($upgrade_info['branch'])) {
            $content .= '<div style="clear:both">
				<label class="label-small">'.$this->trans('Branch:', array(), 'Modules.Autoupgrade.Admin').'</label>
					<span class="available">
						<img src="../img/admin/'.(!empty($upgrade_info['available'])?'enabled':'disabled').'.gif" />'
                .' '.(!empty($upgrade_info['available'])?$this->trans('available', array(), 'Modules.Autoupgrade.Admin'):$this->trans('unavailable', array(), 'Modules.Autoupgrade.Admin')).'
					</span>
				</div>';
        }
        $content .= '<div class="all-infos">';
        if (isset($upgrade_info['version_name'])) {
            $content .= '<div style="clear:both;">
			<label class="label-small">'.$this->trans('Name:', array(), 'Admin.Global').'</label>
				<span class="name">'.$upgrade_info['version_name'].'&nbsp;</span>
            </div>';
        }
        if (isset($upgrade_info['version_number'])) {
            $content .= '<div style="clear:both;">
			<label class="label-small">'.$this->trans('Version number:', array(), 'Modules.Autoupgrade.Admin').'</label>
				<span class="version">'.$upgrade_info['version_num'].'&nbsp;</span>
            </div>';
        }
        if (!empty($upgrade_info['link'])) {
            $content .= '<div style="clear:both;">
			<label class="label-small">'.$this->trans('URL:', array(), 'Modules.Autoupgrade.Admin').'</label>
                <a class="url" href="'.$upgrade_info['link'].'">'.$upgrade_info['link'].'</a>
            </div>';
        }
        if (!empty($upgrade_info['md5'])) {
            $content .= '<div style="clear:both;">
			<label class="label-small">'.$this->trans('MD5 hash:', array(), 'Modules.Autoupgrade.Admin').'</label>
				<span class="md5">'.$upgrade_info['md5'].'&nbsp;</span>
            </div>';
        }

        if (!empty($upgrade_info['changelog'])) {
            $content .= '<div style="clear:both;">
			<label class="label-small">'.$this->trans('Changelog:', array(), 'Modules.Autoupgrade.Admin').'</label>
				<a class="changelog" href="'.$upgrade_info['changelog'].'">'.$this->trans('see changelog', array(), 'Modules.Autoupgrade.Admin').'</a>
			</div>';
        }

        $content .= '</div>
          </div>';
        return $content;
    }

    public function displayDevTools()
    {
        $content = '';
        $content .= '<br class="clear"/>';
        $content .= '<fieldset class="autoupgradeSteps"><legend>'.$this->trans('Step', array(), 'Modules.Autoupgrade.Admin').'</legend>';
        $content .= '<h4>'.$this->trans('Upgrade steps', array(), 'Modules.Autoupgrade.Admin').' : </h4>';
        $content .= '<div>';
        $content .= '<a id="download" class="upgradestep">download</a>';
        $content .= '<a id="unzip" class="upgradestep">unzip</a>'; // unzip in autoupgrade/latest
        $content .= '<a id="removeSamples" class="upgradestep">removeSamples</a>'; // remove samples (iWheel images)
        $content .= '<a id="backupFiles" class="upgradestep">backupFiles</a>'; // backup files
        $content .= '<a id="backupDb" class="upgradestep">backupDb</a>';
        $content .= '<a id="upgradeFiles" class="upgradestep">upgradeFiles</a>';
        $content .= '<a id="upgradeDb" class="upgradestep">upgradeDb</a>';
        $content .= '<a id="upgradeModules" class="upgradestep">upgradeModules</a>';
        $content .= '<a id="cleanDatabase" class="upgradestep">cleanDb</a>';
        $content .= '<a id="upgradeComplete" class="upgradestep">upgradeComplete</a>';
        $content .= '</div></fieldset>';

        return $content;
    }

    private function _displayBlockActivityLog()
    {
        $this->_html .= '<div class="bootstrap" id="activityLogBlock" style="display:none">
           <div class="panel">
                <div class="panel-heading">
                    '.$this->trans('Activity Log', array(), 'Modules.Autoupgrade.Admin').'
                </div>
                <p id="upgradeResultCheck" style="display: none;" class="alert alert-success"></p>

                <div><div id="upgradeResultToDoList" style="display: none;" class="alert alert-info col-xs-12"></div></div><br>

                <div class="row">
                    <div id="currentlyProcessing" class="col-xs-12" style="display:none;">
                        <h4 id="pleaseWait">'.$this->trans('Currently processing', array(), 'Modules.Autoupgrade.Admin').' <img class="pleaseWait" src="'.__PS_BASE_URI__.'img/loader.gif"/></h4>
                        <div id="infoStep" class="processing" >'.$this->trans('Analyzing the situation...', array(), 'Modules.Autoupgrade.Admin').'</div>
                    </div>
                </div><br>';

        $this->_html .= '<div id="quickInfo" class="clear processing col-xs-12"></div>';

        // this block will show errors and important warnings that happens during upgrade
        $this->_html .= '<div class="row">
            <div id="errorDuringUpgrade" class="col-xs-12" style="display:none;">
                <h4>'.$this->trans('Errors', array(), 'Modules.Autoupgrade.Admin').'</h4>
                <div id="infoError" class="processing" ></div>
            </div>
        </div>';

        $this->_html .= '</div></div>';
    }

    public function display()
    {
        /* Make sure the user has configured the upgrade options, or set default values */
        $configuration_keys = array(
            'PS_AUTOUP_UPDATE_DEFAULT_THEME' => 1,
            'PS_AUTOUP_CHANGE_DEFAULT_THEME' => 0,
            'PS_AUTOUP_KEEP_MAILS' => 0,
            'PS_AUTOUP_CUSTOM_MOD_DESACT' => 1,
            'PS_AUTOUP_MANUAL_MODE' => 0,
            'PS_AUTOUP_PERFORMANCE' => 1,
        );

        foreach ($configuration_keys as $k => $default_value) {
            if (Configuration::get($k) == '') {
                Configuration::updateValue($k, $default_value);
            }
        }

        // Using independant template engine for 1.6 & 1.7 compatibility
        $loader = new Twig_Loader_Filesystem();
        $loader->addPath(realpath(__DIR__).'/views/templates', 'ModuleAutoUpgrade');
        $twig = new Twig_Environment($loader, array(
            //'cache' => '/path/to/compilation_cache',
        ));
        $twig->addExtension(new TransFilterExtension($this->getTranslator()));

        // update backup name
        $backupFinder = new BackupFinder($this->backupPath);
        $availableBackups = $backupFinder->getAvailableBackups();
        if (!$this->upgradeConfiguration->get('PS_AUTOUP_BACKUP')
            && !empty($availableBackups)
            && !in_array($this->backupName, $availableBackups)
        ) {
            $this->backupName = end($availableBackups);
        }
        
        $upgrader = $this->getUpgrader();
        $upgradeSelfCheck = new UpgradeSelfCheck(
            $upgrader,
            $this->prodRootDir,
            $this->adminDir,
            $this->autoupgradePath
        );
        $this->_html = (new UpgradePage(
            $this->upgradeConfiguration,
            $twig,
            $this->getTranslator(),
            $upgradeSelfCheck,
            $upgrader,
            $backupFinder,
            $this->autoupgradePath,
            $this->prodRootDir,
            $this->adminDir,
            self::$currentIndex,
            $this->token,
            $this->install_version,
            $this->manualMode,
            $this->backupName,
            $this->downloadPath
        ))->display(
            $this->buildAjaxResult()
        );
        
        $this->ajax = true;
        $this->content = $this->_html;
        return parent::display();
    }

    /**
     * @desc extract a zip file to the given directory
     * @return bool success
     * we need a copy of it to be able to restore without keeping Tools and Autoload stuff
     */
    private function ZipExtract($from_file, $to_dir)
    {
        if (!is_file($from_file)) {
            $this->next = 'error';
            $this->nextQuickInfo[] = $this->trans('%s is not a file', array($from_file), 'Modules.Autoupgrade.Admin');
            $this->nextErrors[] = $this->trans('%s is not a file', array($from_file), 'Modules.Autoupgrade.Admin');
            return false;
        }

        if (!file_exists($to_dir)) {
            if (!mkdir($to_dir)) {
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->trans('Unable to create directory %s.', array($to_dir), 'Modules.Autoupgrade.Admin');
                $this->nextErrors[] = $this->trans('Unable to create directory %s.', array($to_dir), 'Modules.Autoupgrade.Admin');
                return false;
            } else {
                chmod($to_dir, 0775);
            }
        }

        $res = false;
        if (!self::$force_pclZip && class_exists('ZipArchive', false)) {
            $this->nextQuickInfo[] = $this->trans('Using class ZipArchive...', array(), 'Modules.Autoupgrade.Admin');
            $zip = new ZipArchive();
            if ($zip->open($from_file) === true && isset($zip->filename) && $zip->filename) {
                $extract_result = true;
                $res = true;
                // We extract file by file, it is very fast
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $extract_result &= $zip->extractTo($to_dir, array($zip->getNameIndex($i)));
                }

                if ($extract_result) {
                    $this->nextQuickInfo[] = $this->trans('Archive extracted', array(), 'Modules.Autoupgrade.Admin');
                    return true;
                } else {
                    $this->nextQuickInfo[] = $this->trans('zip->extractTo(): unable to use %s as extract destination.', array($to_dir), 'Modules.Autoupgrade.Admin');
                    $this->nextErrors[] = $this->trans('zip->extractTo(): unable to use %s as extract destination.', array($to_dir), 'Modules.Autoupgrade.Admin');
                    return false;
                }
            } elseif (isset($zip->filename) && $zip->filename) {
                $this->nextQuickInfo[] = $this->trans('Unable to open zipFile %s', array($from_file), 'Modules.Autoupgrade.Admin');
                $this->nextErrors[] = $this->trans('Unable to open zipFile %s', array($from_file), 'Modules.Autoupgrade.Admin');
                return false;
            }
        }
        if (!$res) {
            if (!class_exists('PclZip', false)) {
                require_once(_PS_ROOT_DIR_.'/modules/autoupgrade/classes/pclzip.lib.php');
            }

            $this->nextQuickInfo[] = $this->trans('Using class PclZip...', array(), 'Modules.Autoupgrade.Admin');

            $zip = new PclZip($from_file);

            if (($file_list = $zip->listContent()) == 0) {
                $this->next = 'error';
                $this->nextQuickInfo[] = $this->trans('[ERROR] Error on extracting archive using PclZip: %s.', array($zip->errorInfo(true)), 'Modules.Autoupgrade.Admin');
                return false;
            }

            // PCL is very slow, so we need to extract files 500 by 500
            $i = 0;
            $j = 1;
            foreach ($file_list as $file) {
                if (!isset($indexes[$i])) {
                    $indexes[$i] = array();
                }
                $indexes[$i][] = $file['index'];
                if ($j++ % 500 == 0) {
                    $i++;
                }
            }

            // replace also modified files
            foreach ($indexes as $index) {
                if (($extract_result = $zip->extract(PCLZIP_OPT_BY_INDEX, $index, PCLZIP_OPT_PATH, $to_dir, PCLZIP_OPT_REPLACE_NEWER)) == 0) {
                    $this->next = 'error';
                    $this->nextErrors[] = $this->trans('[ERROR] Error on extracting archive using PclZip: %s.', array($zip->errorInfo(true)), 'Modules.Autoupgrade.Admin');
                    return false;
                } else {
                    foreach ($extract_result as $extractedFile) {
                        $file = str_replace($this->prodRootDir, '', $extractedFile['filename']);
                        if ($extractedFile['status'] != 'ok' && $extractedFile['status'] != 'already_a_directory') {
                            $this->nextQuickInfo[] = $this->trans('[ERROR] %file% has not been unzipped: %status%', array('%file%' => $file, '%status%' => $extractedFile['status']), 'Modules.Autoupgrade.Admin');
                            $this->nextErrors[] = $this->trans('[ERROR] %file% has not been unzipped: %status%', array('%file%' => $file, '%status%' => $extractedFile['status']), 'Modules.Autoupgrade.Admin');
                            $this->next = 'error';
                        } else {
                            $this->nextQuickInfo[] = sprintf('%1$s unzipped into %2$s', $file, str_replace(_PS_ROOT_DIR_, '', $to_dir));
                        }
                    }
                    if ($this->next === 'error') {
                        return false;
                    }
                }
            }
            return true;
        }
    }

    private function _listArchivedFiles($zipfile)
    {
        if (file_exists($zipfile)) {
            $res = false;
            if (!self::$force_pclZip && class_exists('ZipArchive', false)) {
                $this->nextQuickInfo[] = $this->trans('Using class ZipArchive...', array(), 'Modules.Autoupgrade.Admin');
                $files = array();
                $zip = new ZipArchive();
                $res = $zip->open($zipfile);
                if ($res) {
                    $res = (isset($zip->filename) && $zip->filename) ? true : false;
                }
                if ($zip && $res === true) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $files[] = $zip->getNameIndex($i);
                    }
                    return $files;
                } elseif ($res) {
                    $this->nextQuickInfo[] = $this->trans('[ERROR] Unable to list archived files', array(), 'Modules.Autoupgrade.Admin');
                    return false;
                }
            }
            if (!$res) {
                $this->nextQuickInfo[] = $this->trans('Using class PclZip...', array(), 'Modules.Autoupgrade.Admin');
                if (!class_exists('PclZip', false)) {
                    require_once(dirname(__FILE__).'/classes/pclzip.lib.php');
                }
                if ($zip = new PclZip($zipfile)) {
                    return $zip->listContent();
                }
            }
        }
        return false;
    }

    /**
     *	bool _skipFile : check whether a file is in backup or restore skip list
     *
     * @param type $file : current file or directory name eg:'.svn' , 'settings.inc.php'
     * @param type $fullpath : current file or directory fullpath eg:'/home/web/www/prestashop/app/config/parameters.php'
     * @param type $way : 'backup' , 'upgrade'
     */
    protected function _skipFile($file, $fullpath, $way = 'backup')
    {
        $fullpath = str_replace('\\', '/', $fullpath); // wamp compliant
        $rootpath = str_replace('\\', '/', $this->prodRootDir);
        $admin_dir = str_replace($this->prodRootDir, '', $this->adminDir);
        switch ($way) {
            case 'backup':
                if (in_array($file, $this->backupIgnoreFiles)) {
                    return true;
                }

                foreach ($this->backupIgnoreAbsoluteFiles as $path) {
                    $path = str_replace(DIRECTORY_SEPARATOR.'admin', DIRECTORY_SEPARATOR.$admin_dir, $path);
                    if ($fullpath == $rootpath.$path) {
                        return true;
                    }
                }
                break;
                // restore or upgrade way : ignore the same files
                // note the restore process use skipFiles only if xml md5 files
                // are unavailable
            case 'restore':
                if (in_array($file, $this->restoreIgnoreFiles)) {
                    return true;
                }

                foreach ($this->restoreIgnoreAbsoluteFiles as $path) {
                    $path = str_replace(DIRECTORY_SEPARATOR.'admin', DIRECTORY_SEPARATOR.$admin_dir, $path);
                    if ($fullpath == $rootpath.$path) {
                        return true;
                    }
                }
                break;
            case 'upgrade':
                if (in_array($file, $this->excludeFilesFromUpgrade)) {
                    if ($file[0] != '.') {
                        $this->nextQuickInfo[] = $this->trans('File %s is preserved', array($file), 'Modules.Autoupgrade.Admin');
                    }
                    return true;
                }

                foreach ($this->excludeAbsoluteFilesFromUpgrade as $path) {
                    $path = str_replace(DIRECTORY_SEPARATOR.'admin', DIRECTORY_SEPARATOR.$admin_dir, $path);
                    if (strpos($fullpath, $rootpath.$path) !== false) {
                        $this->nextQuickInfo[] = $this->trans('File %s is preserved', array($fullpath), 'Modules.Autoupgrade.Admin');
                        return true;
                    }
                }

                break;
                // default : if it's not a backup or an upgrade, do not skip the file
            default:
                return false;
        }
        // by default, don't skip
        return false;
    }

    private function getThemeManager()
    {
        $id_employee = $_COOKIE['id_employee'];

        $context = Context::getContext();
        $context->employee = new Employee((int) $id_employee);

        return (new \PrestaShop\PrestaShop\Core\Addon\Theme\ThemeManagerBuilder($context, $this->db))->build();
    }

    private function clearMigrationCache()
    {
        Tools::clearCache();
        Tools::clearXMLCache();
        Media::clearCache();
        Tools::generateIndex();

        $sf2Refresh = new \PrestaShopBundle\Service\Cache\Refresh();
        $sf2Refresh->addCacheClear(_PS_MODE_DEV_ ? 'dev' : 'prod');
        $sf2Refresh->execute();
    }

    private function getUpgrader()
    {
        if (!is_null($this->upgrader)) {
            return $this->upgrader;
        }
        // in order to not use Tools class
        $upgrader = new Upgrader();
        preg_match('#([0-9]+\.[0-9]+)(?:\.[0-9]+){1,2}#', _PS_VERSION_, $matches);
        $upgrader->branch = $matches[1];
        $channel = $this->upgradeConfiguration->get('channel');
        switch ($channel) {
            case 'archive':
                $upgrader->channel = 'archive';
                $upgrader->version_num = $this->upgradeConfiguration->get('archive.version_num');
                $upgrader->checkPSVersion(true, array('archive'));
                break;
            case 'directory':
                $upgrader->channel = 'directory';
                $upgrader->version_num = $this->upgradeConfiguration->get('directory.version_num');
                $upgrader->checkPSVersion(true, array('directory'));
                break;
            default:
                $upgrader->channel = $channel;
                if (isset($_GET['refreshCurrentVersion'])) {
                    // delete the potential xml files we saved in config/xml (from last release and from current)
                    $upgrader->clearXmlMd5File(_PS_VERSION_);
                    $upgrader->clearXmlMd5File($upgrader->version_num);
                    if ($this->upgradeConfiguration->get('channel') == 'private' && !$this->upgradeConfiguration->get('private_allow_major')) {
                        $upgrader->checkPSVersion(true, array('private', 'minor'));
                    } else {
                        $upgrader->checkPSVersion(true, array('minor'));
                    }
                    Tools14::redirectAdmin($this->currentIndex.'&conf=5&token='.Tools14::getValue('token'));
                } else {
                    if ($this->upgradeConfiguration->get('channel') == 'private' && !$this->upgradeConfiguration->get('private_allow_major')) {
                        $upgrader->checkPSVersion(false, array('private', 'minor'));
                    } else {
                        $upgrader->checkPSVersion(false, array('minor'));
                    }
                }
                $this->install_version = $upgrader->version_num;
        }
        $this->upgrader = $upgrader;
        return $this->upgrader;
    }

    public function getTranslator()
    {
        // TODO: 1.7 Only
        return Context::getContext()->getTranslator();
    }
}
