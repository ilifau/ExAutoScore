<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once ('./Modules/Exercise/classes/class.ilAssignmentHookPlugin.php');
/**
 * Plugin Creation of test export
 */
class ilExAutoScorePlugin extends ilAssignmentHookPlugin
{
    /**
     * Get Plugin Name. Must be same as in class name il<Name>Plugin
     * and must correspond to plugins subdirectory name.
     * @return    string    Plugin Name
     */
    function getPluginName() {
        return "ExAutoScore";
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
}