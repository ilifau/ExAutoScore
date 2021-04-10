<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once (__DIR__ . '/models/class.ilExAutoScoreAssignment.php');
require_once (__DIR__ . '/traits/trait.ilExAutoScoreGUIBase.php');
require_once (__DIR__ . '/class.ilExAutoScoreConnector.php');

/**
 * Specific settings of an auto score assignment
 *
 * @ilCtrl_isCalledBy ilExAutoScoreSettingsGUI: ilExAssTypeAutoScoreUserGUI, ilExAssTypeAutoScoreTeamGUI
 */
class ilExAutoScoreSettingsGUI
{
    use ilExAutoScoreGUIBase;

    /** @var ilExAutoScorePlugin */
    protected $plugin;

    /** @var ilExAssignment */
    protected $assignment;

    /**
     * Constructor
     *
     * @param ilExAutoScorePlugin
     */
    public function __construct(ilExAutoScorePlugin $plugin, ilExAssignment $assignment)
    {
        $this->initGlobals();
        $this->plugin = $plugin;
        $this->assignment = $assignment;
    }

    /**
     * Execute command
     */
    public function executeCommand()
    {
        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd('showSettings');
        $this->setToolbar();

        switch ($next_class) {

            default:
                switch ($cmd) {
                    case 'showSettings':
                    case 'saveSettings':
                    case 'sendAssignment':
                    case 'sendExampleTask':
                        $this->$cmd();
                        break;
                    default:
                        $this->tpl->setContent($cmd);
                }
        }
    }

    /**
     * Show the settings
     */
    public function showSettings()
    {
        $form = $this->initSettingsForm();
        $this->tpl->setContent($form->getHTML());
    }


    /**
     * Save the settings
     */
    public function saveSettings()
    {
        global $DIC;

        $form = $this->initSettingsForm();
        $form->setValuesByPost();
        if ($form->checkInput()) {
            $assAuto = ilExAutoScoreAssignment::findOrGetInstance($this->assignment->getId());
            $assContOld = ilExAutoScoreProvidedFile::getAssignmentDocker($this->assignment->getId());

            $request = $DIC->http()->request();
            $params = $request->getParsedBody();

            if (!empty($params['exautoscore_docker_upload']['tmp_name'])) {
                $assCont = new ilExAutoScoreProvidedFile();
                $assCont->setAssignmentId($this->assignment->getId());
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
            $assAuto->save();

            ilUtil::sendSuccess($this->lng->txt('settings_saved'), true);
            $this->ctrl->redirect($this, 'showSettings');
        }
        else {
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
        }
   }


    /**
     * @return ilPropertyFormGUI
     */
    public function initSettingsForm()
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->plugin->txt('autoscore_settings'));
        $form->addCommandButton('saveSettings', $this->lng->txt('save_settings'));

        $assAuto = ilExAutoScoreAssignment::findOrGetInstance($this->assignment->getId());
        $assCont = ilExAutoScoreProvidedFile::getAssignmentDocker($this->assignment->getId());

        $contUploadFile = new ilFileInputGUI($this->plugin->txt('docker_upload'), 'exautoscore_docker_upload');
        $contUploadFile->setInfo($this->plugin->txt('docker_upload_info'));
        $form->addItem($contUploadFile);

        $contExistingFilename = new ilNonEditableValueGUI($this->plugin->txt('existing_file'), 'exautoscore_docker_filename');
        $contExistingFilename->setValue($assCont->getFilename());
        $form->addItem($contExistingFilename);

        $contDescription = new ilTextAreaInputGUI($this->lng->txt('description'),'exautoscore_docker_description');
        $contDescription->setInfo($this->plugin->txt('docker_description_info'));
        $contDescription->setValue($assCont->getDescription());
        $form->addItem($contDescription);

        $contCommand = new ilTextInputGUI($this->plugin->txt('docker_command'), 'exautoscore_docker_command');
        $contCommand->setInfo($this->plugin->txt('docker_command_info'));
        $contCommand->setValue($assAuto->getCommand());
        $form->addItem($contCommand);

        $headAssResult = new ilFormSectionHeaderGUI();
        $headAssResult->setTitle($this->plugin->txt('head_send_assignment_result'));
        $form->addItem($headAssResult);

        $assUuid = new ilNonEditableValueGUI($this->plugin->txt('assignment_uuid'), 'exautoscore_assignment_uuid');
        $assUuid->setInfo($this->plugin->txt('assignment_uuid_info'));
        $assUuid->setValue($assAuto->getUuid());
        $form->addItem($assUuid);

        return $form;
    }


    public function sendAssignment()
    {
        $connector = new ilExAutoScoreConnector();
        $result = $connector->sendAssignment($this->assignment);
        ilUtil::sendInfo('<pre>' . print_r($result, true) . '</pre>', true);
        $this->ctrl->redirect($this, 'showSettings');
    }


    public function sendExampleTask()
    {
        global $DIC;
        $connector = new ilExAutoScoreConnector();
        $result = $connector->sendTask($this->assignment, $DIC->user());
        ilUtil::sendInfo('<pre>' . print_r($result, true) . '</pre>', true);
        $this->ctrl->redirect($this, 'showSettings');
    }


    public function setToolbar()
    {
        $button = ilLinkButton::getInstance();
        $button->setCaption($this->plugin->txt('send_assignment'), false);
        $button->setUrl($this->ctrl->getLinkTarget($this, 'sendAssignment'));
        $this->toolbar->addButtonInstance($button);

        $button = ilLinkButton::getInstance();
        $button->setCaption($this->plugin->txt('send_example_task'), false);
        $button->setUrl($this->ctrl->getLinkTarget($this, 'sendExampleTask'));
        $this->toolbar->addButtonInstance($button);
    }


}
