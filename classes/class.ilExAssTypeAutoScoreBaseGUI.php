<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once (__DIR__ . '/models/class.ilExAutoScoreAssignment.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreProvidedFile.php');
require_once (__DIR__ . '/traits/trait.ilExAutoScoreGUIBase.php');

/**
 * Auto Score Base Assignment Type GUI
 * (control structure is provided in child classes)
 */
abstract class ilExAssTypeAutoScoreBaseGUI implements ilExAssignmentTypeGUIInterface
{
    use ilExAssignmentTypeGUIBase;
    use ilExAutoScoreGUIBase;

    /** @var ilExAutoScorePlugin */
    protected $plugin;

    /**
     * Constructor
     *
     * @param ilExAutoScorePlugin
     */
    public function __construct($plugin)
    {
        $this->initGlobals();
        $this->plugin = $plugin;
    }

    /**
     * Execute command
     */
    public function executeCommand()
    {
        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd();

        switch ($next_class) {
            case 'ilexautoscoresettingsgui':
                require_once(__DIR__ . '/class.ilExAutoScoreSettingsGUI.php');
                $gui = new ilExAutoScoreSettingsGUI($this->plugin, $this->assignment);
                $this->tabs->activateTab('exautoscore_settings');
                $this->ctrl->forwardCommand($gui);
                break;

            case 'ilexautoscoreprovidedfilesgui':
                require_once(__DIR__ . '/class.ilExAutoScoreProvidedFilesGUI.php');
                $gui = new ilExAutoScoreProvidedFilesGUI($this->plugin, $this->assignment);
                $this->tabs->activateTab('exautoscore_provided_files');
                $this->ctrl->forwardCommand($gui);
                break;
            case 'ilexautoscorerequiredfilesgui':
                require_once(__DIR__ . '/class.ilExAutoScoreRequiredFilesGUI.php');
                $gui = new ilExAutoScoreRequiredFilesGUI($this->plugin, $this->assignment);
                $this->tabs->activateTab('exautoscore_required_files');
                $this->ctrl->forwardCommand($gui);
                break;


            default:
                switch ($cmd) {
                    default:
                        if (in_array($cmd, [])) {
                           $this->$cmd();
                        }
                }
        }
    }


    /**
     * @inheritdoc
     */
    public function addEditFormCustomProperties(ilPropertyFormGUI $form, $exercise_id = null, $assignment_id = null)
    {
    }


    /**
     * Get values from form and put them into assignment
     * @param ilExAssignment $ass
     * @param ilPropertyFormGUI $form
     */
    public function importFormToAssignment(ilExAssignment $ass, ilPropertyFormGUI $form)
    {
    }


    /**
     * Get form values array from assignment
     * @param ilExAssignment $ass
     * @return array
     */
    public function getFormValuesArray(ilExAssignment $ass)
    {
        return [];
    }

    /**
     * Add overview content of submission to info screen object
     * @param ilInfoScreenGUI $a_info
     * @param ilExSubmission $a_submission
     */
    public function getOverviewContent(ilInfoScreenGUI $a_info, ilExSubmission $a_submission)
    {
    }


    /**
     * @inheritdoc
     */
    public function handleEditorTabs(ilTabsGUI $tabs)
    {
        $tabs->removeTab('ass_files');

        $tabs->addTab('exautoscore_settings',
            $this->plugin->txt('autoscore_settings'),
            $this->ctrl->getLinkTargetByClass(['ilexassignmenteditorgui', strtolower(get_class($this)),'ilexautoscoresettingsgui']));


        $tabs->addTab('exautoscore_provided_files',
            $this->plugin->txt('provided_files'),
            $this->ctrl->getLinkTargetByClass(['ilexassignmenteditorgui', strtolower(get_class($this)),'ilexautoscoreprovidedfilesgui']));

        $tabs->addTab('exautoscore_required_files',
            $this->plugin->txt('required_files'),
            $this->ctrl->getLinkTargetByClass(['ilexassignmenteditorgui', strtolower(get_class($this)),'ilexautoscorerequiredfilesgui']));
    }

}
