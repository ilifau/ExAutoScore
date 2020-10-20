<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once (__DIR__ . '/models/class.ilExAutoScoreAssignment.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreContainer.php');
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
        $assAuto = ilExAutoScoreAssignment::findOrGetInstance(isset($this->assignment) ? $this->assignment->getId() : 0);
        $assCont = ilExAutoScoreContainer::findOrGetInstance($assAuto->getContainerId());

        $contRadio = new ilRadioGroupInputGUI($this->plugin->txt('execution_container'), 'exautoscore_cont_radio');
        $form->addItem($contRadio);

        // use already selected container
        $contOptExisting = new ilRadioOption($this->plugin->txt('use_existing_file'), 'cont_use_existing');
        $contRadio->addOption($contOptExisting);
        if (empty($assCont->getId())) {
            $contOptExisting->setDisabled(true);
        }
        else {
            $contExistingTitle = new ilNonEditableValueGUI($this->plugin->txt('container'), 'cont_existing_title');
            $contExistingTitle->setValue($assCont->getTitle());
            $contOptExisting->addSubItem($contExistingTitle);

            $contExistingPublic = new ilCheckboxInputGUI($this->plugin->txt('container_public'), 'exautoscore_cont_public');
            $contExistingPublic->setInfo($this->plugin->txt('container_public_info'));
            $contOptExisting->addSubItem($contExistingPublic);

            // container is uploaded with this assignment
            if ($assCont->getOrigExerciseId() == $exercise_id) {
                $contExistingTitle->setInfo($assCont->getFilename() . ' ' . $this->plugin->txt('is_own_container'));
            }
            else {
                $contExistingTitle->setInfo($assCont->getFilename() . ' ' . $this->plugin->txt('is_other_container'));
                $contExistingPublic->setDisabled(true);
            }
        }

        // select an existing container
        $contOptSelect = new ilRadioOption($this->plugin->txt('use_other_file'), 'cont_use_other');
        $contRadio->addOption($contOptSelect);
        $list = $assAuto->getSelectableContainers();
        if (empty($list)) {
            $contOptSelect->setDisabled(true);
        }
        else {
            $options = [];
            foreach ($list as $assCont) {
                $title =  $assCont->getTitle();
                if ($assCont->getOrigExerciseId() == $exercise_id) {
                    $title .= ' '. $this->plugin->txt('is_own_container');
                }
                else {
                    $title .= ' ' . $this->plugin->txt('is_other_container');
                }
                $options[$assCont->getId()] = $title;
            }
            $cont_select = new ilSelectInputGUI($this->plugin->txt('container'), 'exautoscore_cont_select');
            $cont_select->setOptions($options);
            $contOptSelect->addSubItem($cont_select);
        }

        // upload a new container
        $contOptUpload =  new ilRadioOption($this->plugin->txt('upload_new_file'), 'cont_upload_new');
        $contRadio->addOption($contOptUpload);
        $cont_upload_file = new ilFileInputGUI($this->plugin->txt('container_upload'), 'exautoscore_cont_file_upload');
        $cont_upload_file->setRequired(true);
        $cont_upload_title = new ilTextInputGUI($this->lng->txt('title'),'exautoscore_cont_upload_title');
        $cont_upload_title->setSize(50);
        $cont_upload_title->setInfo($this->plugin->txt('cont_upload_title_info'));
        $cont_upload_title->setRequired(true);
        $contOptUpload->addSubItem($cont_upload_file);
        $contOptUpload->addSubItem($cont_upload_title);
    }

    /**
     * @inheritdoc
     */
    public function importFormToAssignment(ilExAssignment $ass, ilPropertyFormGUI $form)
    {
        global $DIC;

        $assAuto = ilExAutoScoreAssignment::findOrGetInstance($ass->getId());
        $request = $DIC->http()->request();
        $params = $request->getParsedBody();

        switch((string) $params['exautoscore_cont_radio'])
        {
            // container is unchanged
            case 'cont_use_existing':
                $assCont = $assAuto->getContainer();
                // container is uploaded in this assignment, so public can be switched
                if ($assCont->getOrigExerciseId() == $ass->getExerciseId()) {
                    $assCont->setPublic((bool) $params['exautoscore_cont_public']);
                    $assCont->save();
                }
                break;

            // other container is selected
            case 'cont_use_other':
                $assCont = ilExAutoScoreContainer::findOrGetInstance((int) $params['exautoscore_cont_select']);
                if ($assCont->getId() && $assCont->getId() != $assAuto->getContainerId()) {
                    if ($assCont->isPublic() || $assCont->getOrigExerciseId() == $ass->getExerciseId()) {
                        $assAuto->removeContainer();
                        $assAuto->setContainerId($assCont->getId());
                        $assAuto->save();
                    }
                }
                break;

            // new container is uploaded
            case 'cont_upload_new':
                if (isset($params['exautoscore_cont_file_upload']['tmp_name'])) {
                    $assCont = new ilExAutoScoreContainer();
                    $assCont->setOrigExerciseId($ass->getExerciseId());
                    $assCont->setTitle((string) $params['exautoscore_cont_upload_title']);
                    $assCont->setPublic(false);
                    $assCont->save();

                    if (!$assCont->storeUploadedFile($params['exautoscore_cont_file_upload']['tmp_name'])) {
                        $assCont->delete();
                    }
                    else {
                        $assAuto->removeContainer();
                        $assAuto->setContainerId($assCont->getId());
                        $assAuto->save();
                    }
                }
                break;
        }
    }

    /**
     * @inheritdoc
     */
    public function getFormValuesArray(ilExAssignment $ass)
    {
        $assAuto = ilExAutoScoreAssignment::findOrGetInstance($ass->getId());
        $assCont = ilExAutoScoreContainer::findOrGetInstance($assAuto->getContainerId());

        return [
            'exautoscore_cont_radio' => empty($assCont->getId()) ? 'cont_upload_new' : 'cont_use_existing',
            'exautoscore_cont_select' => $assCont->getId(),
            'exautoscore_cont_public' => $assCont->isPublic()
        ];
    }

    /**
     * @inheritdoc
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
    }
}
