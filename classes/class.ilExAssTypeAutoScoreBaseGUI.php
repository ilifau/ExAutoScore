<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once (__DIR__ . '/models/class.ilExAutoScoreAssignment.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreTask.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreProvidedFile.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreRequiredFile.php');
require_once (__DIR__ . '/traits/trait.ilExAutoScoreGUIBase.php');

require_once "./Modules/Exercise/AssignmentTypes/GUI/classes/interface.ilExAssignmentTypeExtendedGUIInterface.php";

/**
 * Auto Score Base Assignment Type GUI
 * (control structure is provided in child classes)
 */
abstract class ilExAssTypeAutoScoreBaseGUI implements ilExAssignmentTypeExtendedGUIInterface
{
    use ilExAssignmentTypeGUIBase;
    use ilExAutoScoreGUIBase;

    /** @var ilExAutoScorePlugin */
    protected $plugin;

    /**
     * Constructor
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
        global $DIC;

        $access = false;

        // submission and exercise is provided when command is forwarded in ilExSubmissionGUI
        if (isset($this->submission)) {
            if ($this->submission->canView()) {
                $this->assignment = $this->submission->getAssignment();
                $access = true;
            }
        }
        // only assignment is provided => editor
        elseif (isset($this->assignment)) {
            if ($this->plugin->canDefine()) {
                foreach(ilObject::_getAllReferences($this->assignment->getExerciseId()) as $ref_id) {
                    if ($DIC->access()->checkAccess("write", '', $ref_id)) {
                        $access = true;
                        break;
                    }
                }
            }
        }

        if (!$access) {
            ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
            $this->ctrl->returnToParent($this);
        }

        $this->ctrl->saveParameter($this, 'ass_id');
        $this->ctrl->setParameterByClass("ilObjExerciseGUI", "ass_id", $this->assignment->getId());
        $this->ctrl->setReturnByClass('ilobjexercisegui', 'showOverview');

        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd();

        switch ($next_class) {
            case 'ilexautoscoresettingsgui':
                require_once(__DIR__ . '/class.ilExAutoScoreSettingsGUI.php');
                $gui = new ilExAutoScoreSettingsGUI($this->plugin, $this->assignment, $this);
                $this->tabs->activateTab('exautoscore_settings');
                $this->ctrl->forwardCommand($gui);
                break;

            case 'ilexautoscoreprovidedfilesgui':
                require_once(__DIR__ . '/class.ilExAutoScoreProvidedFilesGUI.php');
                $gui = new ilExAutoScoreProvidedFilesGUI($this->plugin, $this->assignment, $this);
                $this->tabs->activateTab('exautoscore_provided_files');
                $this->ctrl->forwardCommand($gui);
                break;
            case 'ilexautoscorerequiredfilesgui':
                require_once(__DIR__ . '/class.ilExAutoScoreRequiredFilesGUI.php');
                $gui = new ilExAutoScoreRequiredFilesGUI($this->plugin, $this->assignment, $this);
                $this->tabs->activateTab('exautoscore_required_files');
                $this->ctrl->forwardCommand($gui);
                break;

            default:
                switch ($cmd) {
                    case 'submissionScreen':
                    case 'downloadProvidedFile':
                    case 'downloadSubmittedFile':
                    case 'downloadExampleFile':
                    case 'uploadSubmission':
                    case 'sendSubmission':
                    case 'returnToParent':
                        $this->$cmd();
                        break;

                    default:
                        $this->tpl->setContent("Unknown Command: " . ilUtil::secureString($cmd));
                }
        }
    }

    /**
     * @inheritdoc
     */
    public function addEditFormCustomProperties(ilPropertyFormGUI $form, $exercise_id = null, $assignment_id = null)
    {
        // handled on separate screens
    }

    /**
     * Get values from form and put them into assignment
     * @param ilExAssignment $ass
     * @param ilPropertyFormGUI $form
     */
    public function importFormToAssignment(ilExAssignment $ass, ilPropertyFormGUI $form)
    {
        // handled on separate screens
    }

    /**
     * Get form values array from assignment
     * @param ilExAssignment $ass
     * @return array
     */
    public function getFormValuesArray(ilExAssignment $ass)
    {
        // handled on separate screens
        return [];
    }

    /**
     * Add overview content of submission to info screen object
     * @param ilInfoScreenGUI $a_info
     * @param ilExSubmission  $a_submission
     */
    public function getOverviewContent(ilInfoScreenGUI $a_info, ilExSubmission $a_submission)
    {
        // getOverviewSubmission() used instead
    }

    /**
     * @inheritdoc
     */
    public function handleEditorTabs(ilTabsGUI $tabs)
    {
        $tabs->removeTab('ass_files');

        if ($this->plugin->canDefine()) {
            $tabs->addTab('exautoscore_settings',
                $this->plugin->txt('autoscore_settings'),
                $this->ctrl->getLinkTargetByClass(['ilexassignmenteditorgui',
                                                   strtolower(get_class($this)),
                                                   'ilexautoscoresettingsgui'
                ]));

            $tabs->addTab('exautoscore_provided_files',
                $this->plugin->txt('provided_files'),
                $this->ctrl->getLinkTargetByClass(['ilexassignmenteditorgui',
                                                   strtolower(get_class($this)),
                                                   'ilexautoscoreprovidedfilesgui'
                ]));

            $tabs->addTab('exautoscore_required_files',
                $this->plugin->txt('required_files'),
                $this->ctrl->getLinkTargetByClass(['ilexassignmenteditorgui',
                                                   strtolower(get_class($this)),
                                                   'ilexautoscorerequiredfilesgui'
                ]));
        }
    }

    /**
     * Add additional overview content of instructions to info screen object
     * @param ilInfoScreenGUI $a_info
     * @param ilExAssignment  $a_assignment
     */
    public function getOverviewAdditionalInstructions(ilInfoScreenGUI $a_info, ilExAssignment $a_assignment)
    {
        $this->ctrl->setParameterByClass("ilExSubmissionGUI", "ass_id", $a_assignment->getId());

        $files = ilExAutoScoreProvidedFile::getAssignmentPublicFiles($a_assignment->getId());
        $content = [];
        foreach ($files as $file) {
            $this->ctrl->setParameter($this, 'file_id', $file->getId());
            $link = $this->ctrl->getLinkTargetByClass(["ilExSubmissionGUI", strtolower(get_called_class())], 'downloadProvidedFile');
            $entry = '<a href="' . $link . '">' . $file->getFilename() . '</a>';
            if (!empty($file->getDescription())) {
                $entry .= '<br>'. $file->getDescription();
            }
            $content[] = $entry;
        }
        if (!empty($content)) {
            $a_info->addProperty($this->plugin->txt('provided_files'), implode('<p>', $content));
        }
    }

    /**
     * Indicate that the standard submission section should be replaced by an own one
     * @return bool
     */
    public function hasOwnOverviewSubmission() : bool
    {
        return true;
    }

    /**
     * Use a specific submission section on the info screen object (instead of standard)
     * @param ilInfoScreenGUI $a_info
     * @param ilExSubmission  $a_submission
     */
    public function getOverviewSubmission(ilInfoScreenGUI $a_info, ilExSubmission $a_submission)
    {
        if (!$a_submission->canView()) {
            return;
        }

        $this->ctrl->setParameterByClass("ilExSubmissionGUI", "ass_id", $a_submission->getAssignment()->getId());

        if ($a_submission->getAssignment()->hasTeam()) {
            ilExSubmissionTeamGUI::getOverviewContent($a_info, $a_submission);

            // no team yet
            if ($a_submission->hasNoTeamYet()) {
                return;
            }
        }

        $task = ilExAutoScoreTask::getSubmissionTask($a_submission);
        $requiredFiles = ilExAutoScoreRequiredFile::getForAssignment($a_submission->getAssignment()->getId());

        $titles = [];
        $links = [];
        foreach ($a_submission->getFiles() as $file) {
            $this->ctrl->setParameter($this, 'delivered', $file['returned_id']);
            $link = $this->ctrl->getLinkTargetByClass(["ilExSubmissionGUI", strtolower(get_called_class())], 'downloadSubmittedFile');
            $titles[] = $file['filetitle'];
            $links[] = '<a href="' . $link . '">' . $file['filetitle'] . '</a>';
        }
        if (!empty($links)) {
            $a_info->addProperty($this->lng->txt("exc_files_returned"), implode(', ', $links));
        }

        if ($a_submission->canSubmit()) {

            $missing = [];
            foreach ($requiredFiles as $requiredFile) {
                if (!in_array($requiredFile->getFilename(), $titles)) {
                    $missing[] = $requiredFile->getFilename();
                }
            }
            if (!empty($missing)) {
                $a_info->addProperty($this->plugin->txt("files_missing"), implode(', ', $missing));
            }

            $button = ilLinkButton::getInstance();
            $button->setPrimary(true);
            $button->setCaption($this->lng->txt(empty($titles) ? 'exc_hand_in' : 'exc_edit_submission'), false);
            $button->setUrl($this->getSubmissionScreenLinkTarget());
            $content = $button->render();

            $sendLink = $this->ctrl->getLinkTargetByClass(["ilExSubmissionGUI", strtolower(get_called_class())], "sendSubmission");

            if (empty($missing)) {
                if (empty($task->getSubmitTime())) {
                    $button = ilLinkButton::getInstance();
                    $button->setCaption($this->plugin->txt('send_submission'), false);
                    $button->setUrl($sendLink);
                    $content .= ' '. $button->render();
                }
                elseif (empty($task->getReturnTime())) {

                    // allow a re-submission after 1 minute
                    $submit = (new ilDateTime($task->getSubmitTime(), IL_CAL_DATETIME))->get(IL_CAL_UNIX);
                    if (time() > $submit + 60) {
                        $button = ilLinkButton::getInstance();
                        $button->setCaption($this->plugin->txt('send_submission_again'), false);
                        $button->setUrl($sendLink);
                        $content .= ' '. $button->render();
                    }
                }
            }

            $a_info->addProperty('', $content);
        }


        $task = ilExAutoScoreTask::getSubmissionTask($a_submission);


        if (!empty($task->getReturnTime())) {
            $time = new ilDateTime($task->getReturnTime(), IL_CAL_DATETIME);
            $content = ilDatePresentation::formatDate($time);
            $content .= ', ' . sprintf($this->plugin->txt("task_duration_inline"), $task->getTaskDuration());
            $a_info->addProperty($this->plugin->txt("return_time"), $content);

            if (!empty($task->getReturnCode())) {
                $a_info->addProperty($this->plugin->txt("return_code"), $task->getReturnCode());
            }

            $contents = [];
            if (!empty($task->getInstantStatus())) {
                $contents[] = '<span class="ilTag">' . $task->getInstantStatus() . '</span>';
            }
            if (!empty($task->getInstantMessage())) {
                $contents[] = $task->getInstantMessage();
            }
            if (!empty($contents)) {
                $a_info->addProperty($this->plugin->txt("instant_message"), implode(' ', $contents) );
            }

        }
        elseif (!empty($task->getSubmitTime())) {
            $time = new ilDateTime($task->getSubmitTime(), IL_CAL_DATETIME);
            $a_info->addProperty($this->plugin->txt("submit_time"), ilDatePresentation::formatDate($time));
            $a_info->addProperty($this->plugin->txt("submit_message"), $task->getSubmitMessage());
        }

    }

    /**
     * Get additional tutor feedback for the submission
     * @param ilInfoScreenGUI $a_info
     * @param ilExSubmission  $a_submission
     */
    public function getOverviewAdditionalFeedback(ilInfoScreenGUI $a_info, ilExSubmission $a_submission)
    {
        $task = ilExAutoScoreTask::getSubmissionTask($a_submission);

        if (!empty($task->getProtectedStatus())) {
            $a_info->addProperty($this->plugin->txt('protected_status'), '<span class="ilTag">' . $task->getProtectedStatus() . '</span>');
        }

        if (!empty($task->getProtectedFeedbackHtml())) {
            $item_id = "exautoscore_feedback_html_" . $a_submission->getAssignment()->getId();

            $modal = ilModalGUI::getInstance();
            $modal->setId($item_id);
            $modal->setType(ilModalGUI::TYPE_LARGE);
            $modal->setBody(ilUtil::stripScriptHTML($task->getProtectedFeedbackHtml(), $this->plugin->getAllowedTags()));
            $modal->setHeading($this->plugin->txt('protected_feedback_html'));

            $button = ilJsLinkButton::getInstance();
            $button->setCaption($this->plugin->txt('show_extended_feedback'), false);
            $button->setOnClick("$('#$item_id').modal('show')");

            $a_info->addProperty('', $modal->getHTML() . $button->getToolbarHTML());
        }
    }

    /**
     * Indicate that the standard general feedback section should be replaced by an own one
     * @return bool
     */
    public function hasOwnOverviewGeneralFeedback() : bool
    {
        return true;
    }

    /**
     * Get a specific general feedback section on the info screen object (instead of standard)
     * @param ilInfoScreenGUI $a_info
     * @param ilExAssignment  $a_assignment
     */
    public function getOverviewGeneralFeedback(ilInfoScreenGUI $a_info, ilExAssignment $a_assignment)
    {
        $this->ctrl->setParameterByClass("ilExSubmissionGUI", "ass_id", $a_assignment->getId());

        $files = ilExAutoScoreRequiredFile::getForAssignment($a_assignment->getId());
        $content = [];
        foreach ($files as $file) {
            $this->ctrl->setParameter($this, 'file_id', $file->getId());
            $link = $this->ctrl->getLinkTargetByClass(["ilExSubmissionGUI", strtolower(get_called_class())], 'downloadExampleFile');
            $entry = '<a href="' . $link . '">' . $file->getFilename() . '</a>';
            if (!empty($file->getDescription())) {
                $entry .= '<br>'. $file->getDescription();
            }
            $content[] = $entry;
        }
        if (!empty($content)) {
            $a_info->addProperty($this->plugin->txt('example_files'), implode('<p>', $content));
        }
    }

    /**
     * Indicate that the standard submission screen should not be shown
     * @return bool
     */
    public function hasOwnSubmissionScreen() : bool
    {
        return true;
    }

    /**
     * Get the link target to view the submission screen
     * @return string
     */
    public function getSubmissionScreenLinkTarget() : string
    {
        return $this->ctrl->getLinkTargetByClass(["ilExSubmissionGUI", strtolower(get_called_class())], "submissionScreen");
    }


    /**
     * Show the screen to submit files
     */
    protected function submissionScreen()
    {
        $this->handleSubmissionTabs($this->tabs);

        if (!$this->submission->canSubmit()) {
            ilUtil::sendInfo($this->lng->txt("exercise_time_over"));
        }
        else {
            if ($this->submission->canAddFile()) {
                // #15883 - extended deadline warning
                $deadline = $this->assignment->getPersonalDeadline($this->user->getId());
                if ($deadline &&
                    time() > $deadline) {
                    $dl = ilDatePresentation::formatDate(new ilDateTime($deadline, IL_CAL_UNIX));
                    $dl = sprintf($this->lng->txt("exc_late_submission_warning"), $dl);
                    $dl = '<span class="warning">' . $dl . '</span>';
                    $this->toolbar->addText($dl);
                }

                $form = $this->initSubmissionForm();
                $this->tpl->setContent($form->getHTML());
            }
        }
    }

    /**
     * Init the submission form
     * @return ilPropertyFormGUI
     */
    protected function initSubmissionForm()
    {
        $existing = array();
        foreach ($this->submission->getFiles() as $file) {
            $existing[$file["filetitle"]] = $file;
        }

        $requiredFiles = ilExAutoScoreRequiredFile::getForAssignment($this->assignment->getId());

        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt('required_files'));
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->addCommandButton('uploadSubmission', $this->lng->txt('upload'));

        foreach ($requiredFiles as $file) {

            $fileUpload = new ilFileInputGUI($file->getFilename(), 'exautoscore_file_upload_' . $file->getId());
            $info = [];
            if (!empty($file->getMaxSize())) {
                $info[] = '<p>' . sprintf($this->plugin->txt('required_max_size_info'),
                        ceil($file->getMaxSize() / 1000)) . '</p>';
            }
            if (!empty($file->getRequiredEncoding())) {
                $info[] = '<p>' . sprintf($this->plugin->txt('required_encoding_info'),
                        $file->getRequiredEncoding()) . '</p>';
            }
            if (!empty($file->getDescription())) {
                $info[] = '<p>' . $file->getDescription() . '</p>';
            }
            if (!isset($existing[$file->getFilename()])) {
                $fileUpload->setRequired(true);
            } else {
                $this->ctrl->setParameter($this, 'delivered', $existing[$file->getFilename()]['returned_id']);
                $link = $this->ctrl->getLinkTarget($this, 'downloadSubmittedFile');
                $info[] = '<strong>' . sprintf($this->plugin->txt('existing_file_size_info'),
                        ceil(filesize($existing[$file->getFilename()]['filename']) / 1000))
                    . '</strong>, <a href="' . $link . '">' . $this->lng->txt('download') . '</a>';
            }
            $fileUpload->setInfo(implode('', $info));
            $form->addItem($fileUpload);
        }

        return $form;
    }

    protected function uploadSubmission()
    {
        global $DIC;

        $this->handleSubmissionTabs($this->tabs);

        $requiredFiles = ilExAutoScoreRequiredFile::getForAssignment($this->assignment->getId());

        $form = $this->initSubmissionForm();
        $form->setValuesByPost();

        // this checks if required files are missing
        if (!$form->checkInput()) {
            $this->tpl->setContent($form->getHTML());
            return;
        }

        $request = $DIC->http()->request();
        $params = $request->getParsedBody();

        //
        // 1. check if all uploaded files are valid
        //
        $errors = false;
        foreach ($requiredFiles as $required) {
            /** @var ilFormPropertyGUI $item */
            $item = $form->getItemByPostVar('exautoscore_file_upload_' . $required->getId());
            $param = (array) $params['exautoscore_file_upload_' . $required->getId()];

            if (empty($param['tmp_name'])) {
                continue;
            }
            elseif ($param['name'] != $required->getFilename()) {
                $item->setAlert($this->plugin->txt('upload_error_filename'));
                $errors = true;
            }
            elseif (!empty($required->getMaxSize()) && filesize($param['tmp_name']) > $required->getMaxSize()) {
                $item->setAlert($this->plugin->txt('upload_error_max_size'));
                $errors = true;
            }
            elseif (!empty($required->getRequiredEncoding())) {
                $data = file_get_contents($param['tmp_name']);
                if (!mb_check_encoding($data, $required->getRequiredEncoding())) {
                    $item->setAlert($this->plugin->txt('upload_error_encoding'));
                    $errors = true;
                }
            }
        }
        if ($errors) {
            ilUtil::sendFailure($this->plugin->txt('upload_error_file'));
            $this->tpl->setContent($form->getHTML());
            return;
        }

        //
        // 2. Save the newly uploaded files
        //
        $existing = [];
        $required = [];
        $new = [];
        $failed = null;
        foreach ($this->submission->getFiles() as $file) {
            $existing[$file["filetitle"]][] = $file['returned_id'];
        }
        foreach ($requiredFiles as $requiredFile) {
            $required[$requiredFile->getFilename()] = true;
            $param = (array) $params['exautoscore_file_upload_' . $requiredFile->getId()];
            if (!empty($param['tmp_name'])) {
                if ($this->submission->uploadFile($param)) {
                    $new[] = $requiredFile;
                }
                else {
                    $failed = $requiredFile;
                    break;
                }
            }
        }

        //
        //  3. an upload failed => delete the other uploaded files and return with message that nothing has changed
        //
        if (isset($failed)) {
            foreach ($this->submission->getFiles() as $file) {
                if (!is_array($existing[$file["filetitle"]])
                    || !in_array( $file['returned_id'], $existing[$file["filetitle"]])) {
                        $this->submission->deleteSelectedFiles(array($file['returned_id']));
                }
            }

            ilUtil::sendFailure(sprintf($this->plugin->txt("submission_upload_error"), $failed->getFilename()));
            $this->tpl->setContent($form->getHTML());
            return;
        }

        // no new upload => show info
        if (empty($new)) {
            ilUtil::sendFailure($this->plugin->txt("submission_no_upload"));
            $this->tpl->setContent($form->getHTML());
            return;
        }

        //
        // 4. a new file is provided => delete already existing files that are no longer needed
        //
        if (!empty($new)) {
            // files with same name as a newly uploaded file
            foreach($new as $requiredFile) {
                if (is_array($existing[$requiredFile->getFilename()])) {
                    $this->submission->deleteSelectedFiles($existing[$requiredFile->getFilename()]);
                }
            }
            // files that are no longer required
            foreach ($existing as $filename => $returned_ids) {
                if (!isset($required[$filename])) {
                    $this->submission->deleteSelectedFiles($existing[$filename]);
                }
            }

            $task = ilExAutoScoreTask::getSubmissionTask($this->submission);
            $task->clearSubmissionData();
            $task->save();
            $task->updateMemberStatus();
        }

        //
        // 5. Send the submission to the scoring server (this will reset the status)
        //
        $this->sendSubmission();
    }

    /**
     * Send a submission to the service
     */
    protected function sendSubmission() {
        require_once (__DIR__ . '/class.ilExAutoScoreConnector.php');
        $connector = new ilExAutoScoreConnector();
        if ($connector->sendSubmission($this->submission, $this->user)) {
            ilUtil::sendSuccess($this->plugin->txt("submission_success"), true);
        }
        else {
            ilUtil::sendFailure($this->plugin->txt("submission_error"), true);
        }

        $this->returnToParent();
    }

    /**
     * User downloads (own) submitted files
     */
    protected function downloadSubmittedFile()
    {
        $delivered_id = (int) $_REQUEST["delivered"];

        if (!isset($this->submission) || !$this->submission->canView()) {
            ilUtil::sendInfo($this->lng->txt("access_denied"), true);
            $this->returnToParent();
        }

        if (!is_array($delivered_id) && $delivered_id > 0) {
            $delivered_id = [$delivered_id];
        }
        if (count($delivered_id) > 0) {
            $this->submission->downloadFiles($delivered_id);
            exit;
        } else {
            $this->returnToParent();
        }
    }

    /**
     * Download a provided file
     */
    protected function downloadProvidedFile()
    {
        $file = ilExAutoScoreProvidedFile::findOrGetInstance($_REQUEST['file_id']);
        if ($file->getAssignmentId() != $this->assignment->getId()) {
            ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
            $this->returnToParent();
        }

        // general access check is done executeCommand()
        // here we check if an exercise member can view the instructions
        if (isset($this->submission)) {
            $state = ilExcAssMemberState::getInstanceByIds($this->assignment->getId(), $this->user->getId());

            if (!$state->areInstructionsVisible() || !$file->isPublic() || $file->getAssignmentId() != $this->assignment->getId()) {
                ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
                $this->returnToParent();
            }
        }

        $file->downloadFile();
    }

    /**
     * Download a provided file
     */
    protected function downloadExampleFile()
    {
        $file = ilExAutoScoreRequiredFile::findOrGetInstance($_REQUEST['file_id']);
        if ($file->getAssignmentId() != $this->assignment->getId()) {
            ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
            $this->returnToParent();
        }

        // general access check is done executeCommand()
        // here we check if an exercise member can view the example
        if (isset($this->submission)) {

            // global feedback / sample solution
            $access = false;
            if ($file->getAssignmentId() != $this->assignment->getId())
                $access = false;
            elseif ($this->assignment->getFeedbackDate() == ilExAssignment::FEEDBACK_DATE_DEADLINE) {
                $state = ilExcAssMemberState::getInstanceByIds($this->assignment->getId(), $this->user->getId());
                $access = $state->hasSubmissionEndedForAllUsers();
            } elseif ($this->assignment->getFeedbackDate() == ilExAssignment::FEEDBACK_DATE_CUSTOM) {
                $access = $this->assignment->afterCustomDate();
            } else {
                $access = $this->submission->hasSubmitted();
            }

            if (!$access) {
                ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
                $this->returnToParent();
                return;
            }
        }

        $file->downloadFile();
    }

    protected function handleSubmissionTabs(ilTabsGui $tabs)
    {
        $tabs->clearTargets();
        $tabs->setBackTarget(
            $this->lng->txt("back"),
            $this->ctrl->getLinkTarget($this, "returnToParent")
        );

        $tabs->addTab(
            "submission",
            $this->lng->txt("exc_submission"),
            $this->ctrl->getLinkTarget($this, "submissionScreen")
        );
        $tabs->activateTab("submission");

        if ($this->assignment->hasTeam()) {
            ilExSubmissionTeamGUI::handleTabs();
        }
    }


    /**
     * @inheritdoc
     */
    public function modifySubmissionTableActions(ilExSubmission $a_submission, ilAdvancedSelectionListGUI $a_actions)
    {
        global $DIC;

        $task = ilExAutoScoreTask::getSubmissionTask($a_submission);
        if (!empty($task->getProtectedFeedbackHtml())) {

            $modal_id = 'exautoscore_task_' . $task->getId();

            $modal = ilModalGUI::getInstance();
            $modal->setId($modal_id);
            $modal->setType(ilModalGUI::TYPE_LARGE);
            $modal->setBody(ilUtil::stripScriptHTML($task->getProtectedFeedbackHtml(), $this->plugin->getAllowedTags()));
            $modal->setHeading($this->plugin->txt('protected_feedback_html'));

            $this->tpl->addLightbox($modal->getHTML(), 'exautoscore_lightbox_' . $task->getId()) ;

            $a_actions->addItem(
                $this->plugin->txt("protected_feedback_html"),
                "",
                "#",
                "",
                "",
                "",
                "",
                false,
                "$('#$modal_id').modal('show')"
            );
        }
    }


    protected function returnToParent() {
        $this->ctrl->returnToParent($this);
    }
}
