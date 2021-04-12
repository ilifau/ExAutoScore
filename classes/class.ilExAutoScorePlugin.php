<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once ('./Modules/Exercise/classes/class.ilAssignmentHookPlugin.php');
/**
 * Plugin Creation of test export
 */
class ilExAutoScorePlugin extends ilAssignmentHookPlugin
{
    /** @var ilExAutoScoreConfig */
    protected $config;

    /** @var self */
    protected static $instance;


    /**
     * Get Plugin Name. Must be same as in class name il<Name>Plugin
     * and must correspond to plugins subdirectory name.
     * @return    string    Plugin Name
     */
    function getPluginName() {
        return "ExAutoScore";
    }

    /**
     * Uninstall custom data of this plugin
     */
    protected function uninstallCustom()
    {
        global $DIC;
        $db = $DIC->database();

        $db->dropTable('exautoscore_assignment', false);
        $db->dropTable('exautoscore_provided_file', false);
    }

    /**
     * Get the plugin instance
     * @return ilExAutoScorePlugin
     */
    public static function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * Get the plugin configuration
     * @return ilExAutoScoreConfig
     */
    public function getConfig()
    {
        if (!isset($this->config))
        {
            require_once (__DIR__ . '/param/class.ilExAutoScoreConfig.php');
            $this->config = new ilExAutoScoreConfig($this);
        }
        return $this->config;
    }

    /**
     * Get the ids of the available assignment types
     */
    public function getAssignmentTypeIds() {
        return [101, 102];
    }


    /**
     * Get an assignment type by its id
     * @param integer $a_id
     * @return ilExAssignmentTypeInterface
     */
    public function getAssignmentTypeById($a_id) {
        switch ((int) $a_id) {
            case 101:
                require_once(__DIR__ . '/class.ilExAssTypeAutoScoreUser.php');
                return new ilExAssTypeAutoScoreUser($this);

            case 102:
                require_once(__DIR__ . '/class.ilExAssTypeAutoScoreTeam.php');
                return new ilExAssTypeAutoScoreTeam($this);
        }
    }

    /**
     * Get an assignment type GUI by its id
     * @param integer $a_id
     * @return ilExAssignmentTypeGUIInterface
     */
    public function getAssignmentTypeGUIById($a_id) {
        switch ((int) $a_id) {
            case 101:
                require_once(__DIR__ . '/class.ilExAssTypeAutoScoreUserGUI.php');
                return new ilExAssTypeAutoScoreUserGUI($this);

            case 102:
                require_once(__DIR__ . '/class.ilExAssTypeAutoScoreTeamGUI.php');
                return new ilExAssTypeAutoScoreTeamGUI($this);
        }
    }

    /**
     * Get the class names of the assignment type GUIs
     * @return string[] (indexed by type id)
     */
    public function getAssignmentTypeGuiClassNames() {
        return [
            101 => 'ilExAssTypeAutoScoreUserGUI',
            102 => 'ilExAssTypeAutoScoreTeamGUI'
        ];
    }

    /**
     * Get the Url for sending back results
     */
    public function getResultUrl() {
        return ILIAS_HTTP_PATH . '/Customizing/global/plugins/Modules/Exercise/AssignmentHook/ExAutoScore/results.php';
    }

    /**
     * Get the plugin durectory in the file storage
     * @return string
     */
    static function getStorageDirectory() {
        return 'exautoscore';
    }
}