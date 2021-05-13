<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once (__DIR__ . '/models/class.ilExAutoScoreAssignment.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreTask.php');
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
    public function __construct(ilExAutoScorePlugin $plugin, ilExAssignment $assignment, ilExAssTypeAutoScoreBaseGUI $parentGUI)
    {
        $this->initGlobals();
        $this->plugin = $plugin;
        $this->assignment = $assignment;
        $this->parentGUI = $parentGUI;
    }

    /**
     * Execute command
     */
    public function executeCommand()
    {
        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd('showSettings');

        switch ($next_class) {

            default:
                switch ($cmd) {
                    case 'showSettings':
                    case 'saveSettings':
                    case 'sendAssignment':
                    case 'sendExampleTask':
                    case 'confirmSendAllTasks':
                    case 'sendAllTasks':
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
        $this->setToolbar();

        if (ilExAutoScoreTask::hasSubmissions($this->assignment->getId())) {
            ilutil::sendInfo($this->plugin->txt('info_existing_submissions'));
        }

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

            $assAuto->setCommand((string) $params['exautoscore_docker_command']);
            $assAuto->setMinPoints((float) $params['exautoscore_min_points']);
            $assAuto->setFailureMails((string) $params['exautoscore_failure_mails']);
            $assAuto->save();

            if (ilExAutoScoreTask::hasSubmissions($this->assignment->getId())) {
                ilUtil::sendSuccess($this->plugin->txt('correction_settings_saved_with_reset'), true);
            }
            else {
                ilUtil::sendSuccess($this->plugin->txt('correction_settings_saved'), true);
            }
            ilExAutoScoreAssignment::resetCorrection($this->assignment->getId());

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
        $assAuto = ilExAutoScoreAssignment::findOrGetInstance($this->assignment->getId());
        $assCont = ilExAutoScoreProvidedFile::getAssignmentDocker($this->assignment->getId());
        $assTask = ilExAutoScoreTask::getExampleTask($this->assignment->getId());

        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->plugin->txt('autoscore_settings'));
        $form->addCommandButton('saveSettings', $this->lng->txt('save_settings'));

        $contUploadFile = new ilFileInputGUI($this->plugin->txt('docker_upload'), 'exautoscore_docker_upload');
        $info = $this->plugin->txt('docker_upload_info');
        if (!empty($assCont->getId())) {
            $this->ctrl->setParameter($this->parentGUI, 'file_id', $assCont->getId());
            $link = $this->ctrl->getLinkTarget($this->parentGUI, 'downloadProvidedFile');
            $info .= '<p><strong>' . $this->plugin->txt('existing_file') . ':</strong> '
                    .'<a href="' . $link . '">' . $assCont->getFilename() . '</a></p>';
        }
        $contUploadFile->setInfo($info);
        $form->addItem($contUploadFile);

        $contDescription = new ilTextAreaInputGUI($this->lng->txt('description'),'exautoscore_docker_description');
        $contDescription->setInfo($this->plugin->txt('docker_description_info'));
        $contDescription->setValue($assCont->getDescription());
        $form->addItem($contDescription);

        $contCommand = new ilTextInputGUI($this->plugin->txt('docker_command'), 'exautoscore_docker_command');
        $contCommand->setInfo($this->plugin->txt('docker_command_info'));
        $contCommand->setValue($assAuto->getCommand());
        $form->addItem($contCommand);

        $minPoints = new ilNumberInputGUI($this->plugin->txt('min_points'), 'exautoscore_min_points');
        $minPoints->setInfo($this->plugin->txt('min_points_info'));
        $minPoints->setValue(empty($assAuto->getMinPoints()) ? null : $assAuto->getMinPoints());
        $minPoints->setDecimals(2);
        $minPoints->setSize(10);
        $form->addItem($minPoints);

        $failureMails = new ilTextInputGUI($this->plugin->txt('failure_mails'), 'exautoscore_failure_mails');
        $failureMails->setInfo($this->plugin->txt('failure_mails_info'));
        $failureMails->setValue($assAuto->getFailureMails());
        $form->addItem($failureMails);


        if (!empty($assAuto->getUuid())) {
            $headAssResult = new ilFormSectionHeaderGUI();
            $headAssResult->setTitle($this->plugin->txt('head_send_assignment_result'));
            $form->addItem($headAssResult);

            $assUuid = new ilNonEditableValueGUI($this->plugin->txt('assignment_uuid'), 'exautoscore_assignment_uuid');
            $assUuid->setInfo($this->plugin->txt('assignment_uuid_info'));
            $assUuid->setValue($assAuto->getUuid());
            $form->addItem($assUuid);

            if (!empty($assTask->getSubmitTime())) {
                $submitTime = new ilNonEditableValueGUI($this->plugin->txt('submit_time'), 'exautoscore_submit_time');
                $submitTime->setValue(ilDatePresentation::formatDate(new ilDateTime($assTask->getSubmitTime(), IL_CAL_DATETIME)));
                $form->addItem($submitTime);
            }

            if (!empty($assTask->getSubmitTime())) {
                $submitSuccess = new ilNonEditableValueGUI($this->plugin->txt('submit_success'), 'exautoscore_submit_success');
                $submitSuccess->setValue($this->lng->txt($assTask->getSubmitSuccess() ? 'yes' : 'no'));
                $form->addItem($submitSuccess);
            }

            if (!empty($assTask->getSubmitMessage())) {
                $submitMessage = new ilNonEditableValueGUI($this->plugin->txt('submit_message'), 'exautoscore_submit_message');
                $submitMessage->setValue($assTask->getSubmitMessage());
                $form->addItem($submitMessage);
            }

            if (!empty($assTask->getReturnTime())) {
                $returnTime = new ilNonEditableValueGUI($this->plugin->txt('return_time'), 'exautoscore_return_time');
                $returnTime->setValue(ilDatePresentation::formatDate(new ilDateTime($assTask->getReturnTime(), IL_CAL_DATETIME)));
                $form->addItem($returnTime);
            }

            if (!empty($assTask->getReturnCode())) {
                $returnCode = new ilNonEditableValueGUI($this->plugin->txt('return_code'), 'exautoscore_return_code');
                $returnCode->setValue($assTask->getReturnCode());
                $form->addItem($returnCode);
            }

            if (!empty($assTask->getReturnPoints())) {
                $returnPoints = new ilNonEditableValueGUI($this->plugin->txt('return_points'), 'exautoscore_return_points');
                $returnPoints ->setValue($assTask->getReturnPoints());
                $form->addItem($returnPoints);
            }

            if (!empty($assTask->getTaskDuration())) {
                $taskDuration = new ilNonEditableValueGUI($this->plugin->txt('task_duration'), 'exautoscore_task_duration');
                $taskDuration ->setValue($assTask->getTaskDuration());
                $form->addItem($taskDuration);
            }

            if (!empty($assTask->getInstantMessage())) {
                $instantMessage = new ilNonEditableValueGUI($this->plugin->txt('instant_message'), 'exautoscore_instant_message');
                $instantMessage->setValue($assTask->getInstantMessage());
                $form->addItem($instantMessage);
            }

            if (!empty($assTask->getInstantStatus())) {
                $instantStatus = new ilNonEditableValueGUI($this->plugin->txt('instant_status'), 'exautoscore_instant_status', true);
                $instantStatus->setValue('<span class="ilTag">' . $assTask->getInstantStatus() . '</span>');
                $form->addItem($instantStatus);
            }

            if (!empty($assTask->getProtectedStatus())) {
                $protectedStatus = new ilNonEditableValueGUI($this->plugin->txt('protected_status'), 'exautoscore_protected_status', true);
                $protectedStatus->setValue('<span class="ilTag">' . $assTask->getProtectedStatus() . '</span>');
                $form->addItem($protectedStatus);
            }

            if (!empty($assTask->getProtectedFeedbackText())) {
                $protectedFeedbackText = new ilNonEditableValueGUI($this->plugin->txt('protected_feedback_text'), 'exautoscore_protected_feedback_text');
                $protectedFeedbackText->setValue($assTask->getProtectedFeedbackText());
                $form->addItem($protectedFeedbackText);
            }

            if (!empty($assTask->getProtectedFeedbackHtml())) {
                $protectedFeedbackHtml = new ilNonEditableValueGUI($this->plugin->txt('protected_feedback_html'), 'exautoscore_protected_feedback_html', true);

                $item_id = "exautoscore_feedback_html_" . $this->assignment->getId();

                $modal = ilModalGUI::getInstance();
                $modal->setId($item_id);
                $modal->setType(ilModalGUI::TYPE_LARGE);
                $modal->setBody(ilUtil::stripScriptHTML($assTask->getProtectedFeedbackHtml()));
                $modal->setHeading($this->plugin->txt('protected_feedback_html'));

                $button = ilJsLinkButton::getInstance();
                $button->setCaption($this->plugin->txt('show_extended_feedback'), false);
                $button->setOnClick("$('#$item_id').modal('show')");

                $protectedFeedbackHtml->setValue($modal->getHTML() . $button->getToolbarHTML());
                $form->addItem($protectedFeedbackHtml);
            }
        }

        return $form;
    }


    public function sendAssignment()
    {
        $connector = new ilExAutoScoreConnector();
        if ($connector->sendAssignment($this->assignment)) {
            ilUtil::sendSuccess($connector->getResultMessage(), true);
        }
        else {
            ilUtil::sendFailure($connector->getResultMessage(), true);
        }
        $this->ctrl->redirect($this, 'showSettings');
    }


    public function sendExampleTask()
    {
        global $DIC;
        $connector = new ilExAutoScoreConnector();
        if ($connector->sendExampleTask($this->assignment, $DIC->user())) {
            ilUtil::sendSuccess($connector->getResultMessage(), true);
        }
        else {
            ilUtil::sendFailure($connector->getResultMessage(), true);
        }

        $this->ctrl->redirect($this, 'showSettings');
    }


    public function confirmSendAllTasks()
    {
        $gui = new ilConfirmationGUI();
        $gui->setFormAction($this->ctrl->getFormAction($this));
        $gui->setHeaderText($this->plugin->txt('confirm_send_all_tasks'));
        $gui->setConfirm($this->plugin->txt('send'), 'sendAllTasks');
        $gui->setCancel($this->lng->txt('cancel'), 'showSettings');
        $this->tpl->setContent($gui->getHTML());
    }


    public function sendAllTasks()
    {
        global $DIC;

        $connector = new ilExAutoScoreConnector();

        $submissions = [];
        if ($this->assignment->getAssignmentType()->isSubmissionAssignedToTeam()) {
            $teams = ilExAssignmentTeam::getInstancesFromMap($this->assignment->getId());
            /** @var ilExAssignmentTeam $team */
            foreach ($teams as $team) {
               $submissions[] = new ilExSubmission($this->assignment, 0, $team);
            }
        }
        else {
            foreach (ilExerciseMembers::_getMembers($this->assignment->getExerciseId()) as $user_id) {
               $submissions[] = new ilExSubmission($this->assignment, $user_id);
            }
        }

       foreach ($submissions as $submission) {
           if ($submission->hasSubmitted()) {
               $connector->sendSubmission($submission, $DIC->user());
           }
       }

       ilUtil::sendSuccess($this->plugin->txt('all_tasks_sent'), true);
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

        $this->toolbar->addSeparator();

        $button = ilLinkButton::getInstance();
        $button->setCaption($this->plugin->txt('send_all_tasks'), false);
        $button->setUrl($this->ctrl->getLinkTarget($this, 'confirmSendAllTasks'));
        $this->toolbar->addButtonInstance($button);
    }


}
