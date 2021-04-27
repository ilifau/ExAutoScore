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
        // submission and exercise is provided when command is forwareded in ilExSubmissionGUI
        if (isset($this->submission)) {
            $this->assignment = $this->submission->getAssignment();
        }

        $this->ctrl->saveParameter($this, 'ass_id');

        $this->ctrl->setParameterByClass("ilObjExerciseGUI", "ass_id", $this->assignment->getId());
        $this->ctrl->setReturnByClass('ilobjexercisegui', 'showOverview');

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
                    case 'submissionScreen':
                    case 'downloadSubmittedFile':
                    case 'uploadSubmission':
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

    /**
     * Add additional overview content of instructions to info screen object
     * @param ilInfoScreenGUI $a_info
     * @param ilExAssignment  $a_assignment
     */
    public function getOverviewAdditionalInstructions(ilInfoScreenGUI $a_info, ilExAssignment $a_assignment)
    {
        // todo: show public files
        //$a_info->addProperty('getOverviewAdditionalInstructions', 'xx');
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
        global $DIC;
        $lng = $DIC->language();

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

        $titles = array();
        foreach ($a_submission->getFiles() as $file) {
            $titles[] = $file["filetitle"];
        }
        $files_str = implode($titles, "<br>");
        if ($files_str == "") {
            $files_str = $lng->txt("message_no_delivered_files");
        }

        if ($a_submission->canSubmit()) {
            $title = (count($titles) == 0
                ? $lng->txt("exc_hand_in")
                : $lng->txt("exc_edit_submission"));

            $button = ilLinkButton::getInstance();
            $button->setPrimary(true);
            $button->setCaption($title, false);
            $button->setUrl($this->ctrl->getLinkTargetByClass(array("ilExSubmissionGUI",
                                                                    strtolower(get_called_class())
            ), "submissionScreen"));
            $files_str .= "<br><br>" . $button->render();
        } else {
            if (count($titles) > 0) {
                $button = ilLinkButton::getInstance();
                $button->setCaption("already_delivered_files");
                $button->setUrl($this->ctrl->getLinkTargetByClass(array("ilExSubmissionGUI",
                                                                        strtolower(get_called_class())
                ), "submissionScreen"));
                $files_str .= "<br><br>" . $button->render();
            }
        }

        $a_info->addProperty($lng->txt("exc_files_returned"), $files_str);

        $task = ilExAutoScoreTask::geSubmissionTask($a_submission);


        if (!empty($task->getReturnTime())) {
            $time = new ilDateTime($task->getReturnTime(), IL_CAL_DATETIME);
            $a_info->addProperty($this->plugin->txt("return_time"), ilDatePresentation::formatDate($time));

            if (!empty($task->getTaskDuration())) {
                $a_info->addProperty($this->plugin->txt("task_duration"), sprintf('%01.2f', $task->getTaskDuration()));
            }
            if (!empty($task->getReturnCode())) {
                $a_info->addProperty($this->plugin->txt("return_code"), $task->getReturnCode());
            }

            if (!empty($task->getInstantStatus())) {
                $a_info->addProperty($this->plugin->txt("instant_status"), $task->getInstantStatus());
            }
            if (!empty($task->getInstantMessage())) {
                $a_info->addProperty($this->plugin->txt("instant_message"), $task->getInstantMessage());
            }
        }
        elseif (!empty($task->getSubmitTime())) {
            $time = new ilDateTime($task->getSubmitTime(), IL_CAL_DATETIME);
            $a_info->addProperty($this->plugin->txt("submit_time"), ilDatePresentation::formatDate($time));
        }

    }

    /**
     * Get additional tutor feedback for the submission
     * @param ilInfoScreenGUI $a_info
     * @param ilExSubmission  $a_submission
     */
    public function getOverviewAdditionalFeedback(ilInfoScreenGUI $a_info, ilExSubmission $a_submission)
    {
        $task = ilExAutoScoreTask::geSubmissionTask($a_submission);

        if (!empty($task->getProtectedFeedbackHtml())) {
            $item_id = "exautoscore_feedback_html_" . $a_submission->getAssignment()->getId();

            $modal = ilModalGUI::getInstance();
            $modal->setId($item_id);
            $modal->setType(ilModalGUI::TYPE_LARGE);
            $modal->setBody(ilUtil::stripScriptHTML($task->getProtectedFeedbackHtml()));
            $modal->setHeading($this->plugin->txt('protected_feedback_html'));

            $button = ilJsLinkButton::getInstance();
            $button->setCaption($this->plugin->txt('show_feedback'), false);
            $button->setOnClick("$('#$item_id').modal('show')");

            $a_info->addProperty($this->plugin->txt("protected_feedback_html"), $modal->getHTML() . $button->getToolbarHTML());
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
        $a_info->addProperty('getOverviewGeneral    Feedback', 'xx');
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


        $requiredFiles = ilExAutoScoreRequiredFile::getForAssignment($this->assignment->getId());

        $form = $this->initSubmissionForm();
        $form->setValuesByPost();
        if (!$form->checkInput()) {
            $this->tpl->setContent($form->getHTML());
            return;
        }

        $request = $DIC->http()->request();
        $params = $request->getParsedBody();

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


        $existing = [];
        foreach ($this->submission->getFiles() as $file) {
            $existing[$file["filetitle"]][] = $file['returned_id'];
        }

        foreach ($requiredFiles as $required) {
            $param = (array) $params['exautoscore_file_upload_' . $required->getId()];

            if (!empty($param['tmp_name'])) {
                if ($this->submission->uploadFile($param)) {
                    if (is_array($existing[$required->getFilename()])) {
                        $this->submission->deleteSelectedFiles($existing[$required->getFilename()]);
                    }
                }
                else {
                    ilUtil::sendFailure($this->lng->txt("exc_upload_error"));
                    $this->tpl->setContent($form->getHTML());
                    return;
                }
            }
        }

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

        if (!$this->submission->canView()) {
            ilUtil::sendInfo($this->lng->txt("access_denied"));
        }

        if (!is_array($delivered_id) && $delivered_id > 0) {
            $delivered_id = [$delivered_id];
        }
        if (is_array($delivered_id) && count($delivered_id) > 0) {
            $this->submission->downloadFiles($delivered_id);
            exit;
        } else {
            $this->ctrl->redirect($this, "submissionScreen");
        }
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


    protected function returnToParent() {
        $this->ctrl->returnToParent($this);
    }
}
