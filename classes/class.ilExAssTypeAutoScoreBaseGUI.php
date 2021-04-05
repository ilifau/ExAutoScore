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
        $contRadio = new ilRadioGroupInputGUI($this->plugin->txt('dockerfile'), 'exautoscore_docker_radio');
        $form->addItem($contRadio);

        // use already selected image
        $contOptExisting = new ilRadioOption($this->plugin->txt('use_existing_file'), 'existing');
        $contRadio->addOption($contOptExisting);

        $contExistingFilename = new ilNonEditableValueGUI($this->lng->txt('filename'), 'exautoscore_docker_filename');
        $contOptExisting->addSubItem($contExistingFilename);

        $contExistingDescription = new ilTextAreaInputGUI($this->lng->txt('description'),'exautoscore_docker_existing_description');
        $contExistingDescription->setInfo($this->plugin->txt('docker_description_info'));
        $contOptExisting->addSubItem($contExistingDescription);

        // upload a new image
        $contOptUpload =  new ilRadioOption($this->plugin->txt('upload_new_file'), 'new');
        $contRadio->addOption($contOptUpload);

        $contUploadFile = new ilFileInputGUI($this->plugin->txt('docker_upload'), 'exautoscore_docker_upload');
        $contUploadFile->setRequired(true);
        $contOptUpload->addSubItem($contUploadFile);

        $contUploadDescription = new ilTextAreaInputGUI($this->lng->txt('description'),'exautoscore_docker_upload_description');
        $contUploadDescription->setInfo($this->plugin->txt('docker_description_info'));
        $contOptUpload->addSubItem($contUploadDescription);
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


        switch($params['exautoscore_docker_radio']) {
            case 'new':
                if (isset($params['exautoscore_docker_upload']['tmp_name'])) {

                    $assCont = new ilExAutoScoreProvidedFile();
                    $assCont->setAssignmentId($ass->getId());
                    $assCont->setDescription((string) $params['exautoscore_docker_upload_description']);
                    $assCont->setPurpose(ilExAutoScoreProvidedFile::PURPOSE_DOCKER);
                    $assCont->setPublic(false);
                    $assCont->save();

                    if (!$assCont->storeUploadedFile($params['exautoscore_docker_upload']['tmp_name'])) {
                        $assCont->delete();
                    } else {
                        $assContOld->delete();
                    }
                }
                break;

            case 'existing':
                $assContOld->setDescription($params['exautoscore_docker_existing_description']);
                $assContOld->save();
                break;
        }
    }


    /**
     * Get form values array from assignment
     * @param ilExAssignment $ass
     * @return array
     */
    public function getFormValuesArray(ilExAssignment $ass)
    {
        $assCont = ilExAutoScoreProvidedFile::getAssignmentDocker($ass->getId());

        return [
            'exautoscore_docker_radio' => empty($assCont->getId()) ? 'new' : 'existing',
            'exautoscore_docker_filename' => $assCont->getFilename(),
            'exautoscore_docker_existing_description' => $assCont->getDescription()
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
}
