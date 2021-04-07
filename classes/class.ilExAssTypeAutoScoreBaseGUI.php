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
        $contUploadFile = new ilFileInputGUI($this->plugin->txt('docker_upload'), 'exautoscore_docker_upload');
        $contUploadFile->setInfo($this->plugin->txt('docker_upload_info'));
        $form->addItem($contUploadFile);

        $contExistingFilename = new ilNonEditableValueGUI($this->plugin->txt('existing_file'), 'exautoscore_docker_filename');
        $form->addItem($contExistingFilename);

        $contDescription = new ilTextAreaInputGUI($this->lng->txt('description'),'exautoscore_docker_description');
        $contDescription->setInfo($this->plugin->txt('docker_description_info'));
        $form->addItem($contDescription);

        $contCommand = new ilTextInputGUI($this->plugin->txt('docker_command'), 'exautoscore_docker_command');
        $contCommand->setInfo($this->plugin->txt('docker_command_info'));
        $form->addItem($contCommand);

        $assUuid = new ilNonEditableValueGUI($this->plugin->txt('assignment_uuid'), 'exautoscore_assignment_uuid');
        $assUuid->setInfo($this->plugin->txt('assignment_uuid_info'));
        $form->addItem($assUuid);

    }


    /**
     * Get values from form and put them into assignment
     * @param ilExAssignment $ass
     * @param ilPropertyFormGUI $form
     */
    public function importFormToAssignment(ilExAssignment $ass, ilPropertyFormGUI $form)
    {
        global $DIC;

        $assAuto = ilExAutoScoreAssignment::findOrGetInstance($ass->getId());
        $assContOld = ilExAutoScoreProvidedFile::getAssignmentDocker($ass->getId());

        $request = $DIC->http()->request();
        $params = $request->getParsedBody();

        if (!empty($params['exautoscore_docker_upload']['tmp_name'])) {
            $assCont = new ilExAutoScoreProvidedFile();
            $assCont->setAssignmentId($ass->getId());
            $assCont->setDescription((string) $params['exautoscore_docker_description']);
            $assCont->setPurpose(ilExAutoScoreProvidedFile::PURPOSE_DOCKER);
            $assCont->setPublic(false);
            $assCont->save();

            if (!$assCont->storeUploadedFile($params['exautoscore_docker_upload']['tmp_name'])) {
                $assCont->delete();
            } else {
                $assContOld->delete();
            }
        }
        else {
            $assContOld->setDescription($params['exautoscore_docker_description']);
            $assContOld->save();
        }

        $assAuto->setCommand($params['exautoscore_docker_command']);
    }


    /**
     * Get form values array from assignment
     * @param ilExAssignment $ass
     * @return array
     */
    public function getFormValuesArray(ilExAssignment $ass)
    {
        $assAuto = ilExAutoScoreAssignment::findOrGetInstance($ass->getId());
        $assCont = ilExAutoScoreProvidedFile::getAssignmentDocker($ass->getId());

        return [
            'exautoscore_docker_filename' => $assCont->getFilename(),
            'exautoscore_docker_description' => $assCont->getDescription(),
            'exautoscore_docker_command' =>  $assAuto->getCommand(),
            'exautoscore_assignment_uuid' => $assAuto->getUuid()
        ];
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

        $tabs->addTab('exautoscore_provided_files',
            $this->plugin->txt('provided_files'),
            $this->ctrl->getLinkTargetByClass(['ilexassignmenteditorgui', strtolower(get_class($this)),'ilexautoscoreprovidedfilesgui']));

        $tabs->addTab('exautoscore_required_files',
            $this->plugin->txt('required_files'),
            $this->ctrl->getLinkTargetByClass(['ilexassignmenteditorgui', strtolower(get_class($this)),'ilexautoscorerequiredfilesgui']));
    }

}
