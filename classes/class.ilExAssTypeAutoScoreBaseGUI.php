<?php
declare(strict_types=1);

use ILIAS\Exercise\Assignment\PropertyAndActionBuilderUI;

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
 *
 * @ilCtrl_IsCalledBy ilExAssTypeAutoScoreBaseGUI: ilExSubmissionGUI, ilExAssignmentEditorGUI
 */
abstract class ilExAssTypeAutoScoreBaseGUI implements ilExAssignmentTypeExtendedGUIInterface
{
    use ilExAssignmentTypeGUIBase;
    use ilExAutoScoreGUIBase;

    /** @var ilExAutoScorePlugin */
    protected mixed $plugin;

    /** @var ilExSubmission|null */
    protected $submission = null;

    /** @var ilObjExercise|null */
    protected $exercise = null;

    public function __construct($plugin)
    {
        $this->initGlobals();
        $this->plugin = $plugin;
    }

    /**
     * Get the submission object
     * @return ilExSubmission|null
     */
    public function getSubmission(): ?ilExSubmission
    {
        return $this->submission ?? null;
    }

    /**
     * Set the submission object (called by ilExSubmissionGUI)
     * @param ilExSubmission $submission
     */
    public function setSubmission(ilExSubmission $submission): void
    {
        $this->submission = $submission;
        $this->assignment = $submission->getAssignment();
    }

    /**
     * Set the exercise object (called by ilExSubmissionGUI)
     * @param ilObjExercise $exercise
     */
    public function setExercise(ilObjExercise $exercise): void
    {
        $this->exercise = $exercise;
    }

    /**
     * Get styled status display (supports both Unicode symbols and text)
     * @param string $status
     * @return string HTML with styled status
     */
    public function getStyledStatusSymbol(string $status): string
    {
        $trimmed = trim($status);
        $color = '#999';
        $symbol = $trimmed;
        $title = $trimmed;
        
        // Unicode-Symbole (AuDoscore)
        if ($trimmed === '✓' || $trimmed === "\u{2713}") {
            $color = '#5cb85c';
            $title = 'Passed';
        } elseif ($trimmed === '☠' || $trimmed === "\u{2620}") {
            $color = '#d9534f';
            $title = 'Compile Error';
        } elseif ($trimmed === '✗' || $trimmed === "\u{2718}") {
            $color = '#d9534f';
            $title = 'Forbidden Construct';
        } elseif ($trimmed === '!' || $trimmed === "\u{21}") {
            $color = '#f0ad4e';
            $title = 'Test Failed';
        } elseif ($trimmed === '⚠' || $trimmed === "\u{26A0}") {
            $color = '#f0ad4e';
            $title = 'Internal Error';
        }
        // Text-basierte Werte (Standard-Container)
        else {
            $lower = strtolower($trimmed);
            
            if ($lower === 'passed' || $lower === 'success' || $lower === 'ok') {
                $color = '#5cb85c';
                $symbol = '✓';
                $title = 'Passed';
            } elseif ($lower === 'failed' || $lower === 'error' || $lower === 'fail') {
                $color = '#d9534f';
                $symbol = '✗';
                $title = 'Failed';
            } elseif ($lower === 'warning' || $lower === 'partial') {
                $color = '#f0ad4e';
                $symbol = '⚠';
                $title = 'Warning';
            }
        }
        
        return sprintf(
            '<span style="color:%s;font-size:1.5em;font-weight:bold;" title="%s">%s</span>',
            $color,
            htmlspecialchars($title),
            htmlspecialchars($symbol)
        );
    }

    /**
     * Format instant message with monospace styling
     * @param string $message
     * @return string HTML formatted message
     */
    public function formatInstantMessage(string $message): string
    {
        return '<pre style="font-family:\'Courier New\',Consolas,monospace;white-space:pre-wrap;margin:0;padding:8px;background:#f5f5f5;border-radius:3px;font-size:0.9em;">' 
            . htmlspecialchars($message) 
            . '</pre>';
    }

    /**
     * Create extended feedback modal button
     * @param ilExAutoScoreTask $task
     * @param string $item_id_suffix
     * @return string HTML for modal + button
     */
    protected function createFeedbackModal(ilExAutoScoreTask $task, string $item_id_suffix = ''): string
    {
        if (empty($task->getProtectedFeedbackHtml())) {
            return '';
        }

        $item_id = "exautoscore_feedback_modal_" . $task->getId() . $item_id_suffix;

        $modal = ilModalGUI::getInstance();
        $modal->setId($item_id);
        $modal->setType(ilModalGUI::TYPE_LARGE);

        $feedbackHtml = $task->getProtectedFeedbackHtml();

        $feedbackHtml = preg_replace('/<details[^>]*>.*?<\/details>/is', '', $feedbackHtml);

        $modal->setBody(ilUtil::stripScriptHTML($feedbackHtml, $this->plugin->getAllowedTags()));
        $modal->setHeading($this->plugin->txt('protected_feedback_html'));

        $button_html = sprintf(
            '<button type="button" class="btn btn-default" onclick="$(\'#%s\').modal(\'show\'); return false;">%s</button>',
            $item_id,
            $this->plugin->txt('show_extended_feedback')
        );

        return $modal->getHTML() . $button_html;
    }

    /**
     * Check if extended feedback can be shown (based on deadline)
     * @param ilExAssignment $ass
     * @param ilExSubmission $sub
     * @return bool
     */
    protected function canShowExtendedFeedbackByDeadline(ilExAssignment $ass, ilExSubmission $sub): bool
    {
        if ($this->plugin->canDefine()) {
            return true;
        }

        $personal_deadline = $ass->getPersonalDeadline($this->user->getId());

        if (!empty($personal_deadline)) {
            return time() > (int)$personal_deadline;
        }

        return $sub->hasSubmitted();
    }

    public function executeCommand(): void
    {
        global $DIC;
        
        $access = false;

        if (isset($this->submission)) {
            if ($this->submission->canView()) {
                $this->assignment = $this->submission->getAssignment();
                $access = true;
            }
        } elseif (isset($this->assignment)) {
            if ($this->plugin->canDefine()) {
                foreach (ilObject::_getAllReferences($this->assignment->getExerciseId()) as $ref_id) {
                    if ($DIC->access()->checkAccess("write", '', $ref_id)) {
                        $access = true;
                        break;
                    }
                }
            }
        }

        if (!$access) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            $this->ctrl->returnToParent($this);
        }

        $this->ctrl->saveParameter($this, 'ass_id');
        $this->ctrl->setParameterByClass(ilObjExerciseGUI::class, "ass_id", $this->assignment->getId());
        $this->ctrl->setReturnByClass(ilObjExerciseGUI::class, 'showOverview');

        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd('submissionScreen');

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

            case '':
            default:
                switch ($cmd) {
                    case 'viewSubmission':
                    case 'submissionScreen':
                    case 'downloadProvidedFile':
                    case 'downloadSubmittedFile':
                    case 'downloadExampleFile':
                    case 'uploadSubmission':
                    case 'sendSubmission':
                    case 'returnToParent':
                    case 'confirmDeleteSubmission':
                    case 'deleteSubmission':
                        $this->$cmd();
                        break;

                    default:
                        $this->submissionScreen();
                }
        }
    }
    
    public function addEditFormCustomProperties(ilPropertyFormGUI $form, $exercise_id = null, $assignment_id = null): void {}
    public function importFormToAssignment(ilExAssignment $ass, ilPropertyFormGUI $form): void {}
    public function getFormValuesArray(ilExAssignment $ass): array { return []; }

    public function getOverviewContent(ilInfoScreenGUI $a_info, ilExSubmission $a_submission): void
    {
        // getOverviewSubmission() used instead
    }

    public function handleEditorTabs(ilTabsGUI $tabs): void
    {
        $tabs->removeTab('ass_files');

        if ($this->plugin->canDefine()) {
            $tabs->addTab(
                'exautoscore_settings',
                $this->plugin->txt('autoscore_settings'),
                $this->ctrl->getLinkTargetByClass(
                    [ ilExAssignmentEditorGUI::class, strtolower(get_class($this)), ilExAutoScoreSettingsGUI::class ],
                    'showSettings'
                )
            );

            $tabs->addTab(
                'exautoscore_provided_files',
                $this->plugin->txt('provided_files'),
                $this->ctrl->getLinkTargetByClass(
                    [ ilExAssignmentEditorGUI::class, strtolower(get_class($this)), ilExAutoScoreProvidedFilesGUI::class ],
                    'listFiles'
                )
            );

            $tabs->addTab(
                'exautoscore_required_files',
                $this->plugin->txt('required_files'),
                $this->ctrl->getLinkTargetByClass(
                    [ ilExAssignmentEditorGUI::class, strtolower(get_class($this)), ilExAutoScoreRequiredFilesGUI::class ],
                    'listFiles'
                )
            );
        }
    }

    public function getOverviewAdditionalInstructions(ilInfoScreenGUI $a_info, ilExAssignment $a_assignment): void
    {
        $this->ctrl->setParameterByClass(ilExSubmissionGUI::class, "ass_id", $a_assignment->getId());

        $files = ilExAutoScoreProvidedFile::getAssignmentPublicFiles($a_assignment->getId());
        $content = [];
        foreach ($files as $file) {
            $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'file_id', $file->getId());
            $link = $this->ctrl->getLinkTargetByClass(
                [ ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, strtolower(get_class($this)) ],
                'downloadProvidedFile'
            );
            $entry = '<a href="' . $link . '">' . $file->getFilename() . '</a>';
            if (!empty($file->getDescription())) {
                $entry .= '<br>' . $file->getDescription();
            }
            $content[] = $entry;
        }
        if (!empty($content)) {
            $a_info->addProperty($this->plugin->txt('provided_files'), implode('<p>', $content));
        }
    }

    public function hasOwnOverviewSubmission() : bool { return true; }

    public function getOverviewSubmission(ilInfoScreenGUI $a_info, ilExSubmission $a_submission): void
    {
        if (!$a_submission->canView()) {
            return;
        }

        $this->ctrl->setParameterByClass(ilExSubmissionGUI::class, "ass_id", $a_submission->getAssignment()->getId());

        if ($a_submission->getAssignment()->hasTeam()) {
            ilExSubmissionTeamGUI::getOverviewContent($a_info, $a_submission);
            if ($a_submission->hasNoTeamYet()) {
                return;
            }
        }

        $task = ilExAutoScoreTask::getSubmissionTask($a_submission);
        $requiredFiles = ilExAutoScoreRequiredFile::getForAssignment($a_submission->getAssignment()->getId());

        // Dateiliste (unverändert)
        $titles = [];
        $links = [];
        foreach ($a_submission->getFiles() as $file) {
            $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'delivered', $file['returned_id']);
            $link = $this->ctrl->getLinkTargetByClass(
                [ ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, strtolower(get_class($this)) ],
                'downloadSubmittedFile'
            );
            $titles[] = $file['filetitle'];
            $links[] = '<a href="' . $link . '">' . $file['filetitle'] . '</a>';
        }
        
        if (!empty($links)) {
            $a_info->addProperty($this->lng->txt("exc_files_returned"), implode(', ', $links));
        } elseif ($a_submission->canSubmit()) {
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

            $this->ctrl->setParameterByClass(ilExSubmissionGUI::class, "ass_id", $a_submission->getAssignment()->getId());
            $sendLink = $this->ctrl->getLinkTargetByClass(
                [ ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, strtolower(get_class($this)) ],
                "sendSubmission"
            );

            if (empty($missing)) {
                if (empty($task->getSubmitTime())) {
                    $button = ilLinkButton::getInstance();
                    $button->setCaption($this->plugin->txt('send_submission'), false);
                    $button->setUrl($sendLink);
                    $content .= ' ' . $button->render();
                } elseif (empty($task->getReturnTime())) {
                    $submit = (new ilDateTime($task->getSubmitTime(), IL_CAL_DATETIME))->get(IL_CAL_UNIX);
                    if (time() > $submit + 60) {
                        $button = ilLinkButton::getInstance();
                        $button->setCaption($this->plugin->txt('send_submission_again'), false);
                        $button->setUrl($sendLink);
                        $content .= ' ' . $button->render();
                    }
                }
            }

            $a_info->addProperty('', $content);
        }

        // Zeiten und Status (unverändert)
        if (!empty($task->getReturnTime())) {
            $time = new ilDateTime($task->getReturnTime(), IL_CAL_DATETIME);
            $content = ilDatePresentation::formatDate($time);
            $content .= ', ' . sprintf($this->plugin->txt("task_duration_inline"), $task->getTaskDuration());
            $a_info->addProperty($this->plugin->txt("return_time"), $content);

            if (!empty($task->getReturnCode())) {
                $a_info->addProperty($this->plugin->txt("return_code"), (string) $task->getReturnCode());
            }
            
        } elseif (!empty($task->getSubmitTime())) {
            $time = new ilDateTime($task->getSubmitTime(), IL_CAL_DATETIME);
            $a_info->addProperty($this->plugin->txt("submit_time"), ilDatePresentation::formatDate($time));
            
            if (!empty($task->getSubmitMessage())) {
                $a_info->addProperty($this->plugin->txt("submit_message"), $task->getSubmitMessage());
            }
        }

        // GEÄNDERT: Sofortiger Status und Meldung hinzugefügt
        if (!empty($task->getInstantStatus())) {
            $a_info->addProperty(
                $this->plugin->txt("instant_status"), 
                $this->getStyledStatusSymbol($task->getInstantStatus())
            );
        }

        // NEU: Sofortige Meldung hinzugefügt
        if (!empty($task->getInstantMessage())) {
            $a_info->addProperty(
                $this->plugin->txt("instant_message"), 
                $this->formatInstantMessage($task->getInstantMessage())
            );
        }
    }

    public function getOverviewAdditionalFeedback(ilInfoScreenGUI $a_info, ilExSubmission $a_submission): void
    {
        $task = ilExAutoScoreTask::getSubmissionTask($a_submission);
        if (!$task || empty($task->getProtectedFeedbackHtml())) {
            return;
        }

        if (!$this->canShowExtendedFeedbackByDeadline($a_submission->getAssignment(), $a_submission)) {
            return;
        }

        $modalHtml = $this->createFeedbackModal($task);
        if (!empty($modalHtml)) {
            $a_info->addProperty('', $modalHtml);
        }
    }

    public function getOverviewAdditionFeedback(ilInfoScreenGUI $a_info, ilExSubmission $a_submission): void
    {
        $this->getOverviewAdditionalFeedback($a_info, $a_submission);
    }

    public function hasOwnOverviewGeneralFeedback() : bool { return true; }

    public function getOverviewGeneralFeedback(ilInfoScreenGUI $a_info, ilExAssignment $a_assignment): void
    {
        $this->ctrl->setParameterByClass(ilExSubmissionGUI::class, "ass_id", $a_assignment->getId());

        $files = ilExAutoScoreRequiredFile::getForAssignment($a_assignment->getId());
        $content = [];
        foreach ($files as $file) {
            $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'file_id', $file->getId());
            $link = $this->ctrl->getLinkTargetByClass(
                [ ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, strtolower(get_class($this)) ],
                'downloadExampleFile'
            );
            $entry = '<a href="' . $link . '">' . $file->getFilename() . '</a>';
            if (!empty($file->getDescription())) {
                $entry .= '<br>' . $file->getDescription();
            }
            $content[] = $entry;
        }
        if (!empty($content)) {
            $a_info->addProperty($this->plugin->txt('example_files'), implode('<p>', $content));
        }
    }

    public function hasOwnSubmissionScreen() : bool { return true; }

    public function getSubmissionScreenLinkTarget() : string
    {
        global $DIC;
        
        $ass_id = null;
        
        if (isset($this->assignment)) {
            $ass_id = $this->assignment->getId();
        } elseif (isset($this->submission)) {
            $ass_id = $this->submission->getAssignment()->getId();
        } elseif (isset($_GET['ass_id'])) {
            $ass_id = (int) $_GET['ass_id'];
        }
        
        if (empty($ass_id)) {
            throw new Exception('Assignment ID not available');
        }
        
        $this->ctrl->setParameterByClass(ilExSubmissionGUI::class, 'ass_id', $ass_id);
        return $this->ctrl->getLinkTargetByClass(
            [ ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, get_class($this) ],
            ''
        );
    }

    protected function submissionScreen()
    {
        global $DIC;

        $this->handleSubmissionTabs($this->tabs);

        if (!isset($this->submission)) {
            $this->tpl->setOnScreenMessage('failure', 'Interner Fehler: Keine Abgabe gefunden');
            return;
        }

        if (!$this->submission->canSubmit()) {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt("exercise_time_over"));
            return;
        }
        
        $button = ilLinkButton::getInstance();
        $button->setCaption($this->plugin->txt('delete_submission'), false);
        $this->ctrl->setParameterByClass(get_class($this), 'ass_id', $this->assignment->getId());
        $button->setUrl($this->ctrl->getLinkTargetByClass(
            [ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, get_class($this)],
            'confirmDeleteSubmission'
        ));
        $DIC->toolbar()->addButtonInstance($button);

        if (!$this->submission->canAddFile()) {
            $this->tpl->setOnScreenMessage('info', 'Sie können derzeit keine Dateien hochladen.');
            return;
        }
        
        $deadline = $this->assignment->getPersonalDeadline($this->user->getId());
        if ($deadline && time() > $deadline) {
            $dl = ilDatePresentation::formatDate(new ilDateTime($deadline, IL_CAL_UNIX));
            $dl = sprintf($this->lng->txt("exc_late_submission_warning"), $dl);
            $dl = '<span class="warning">' . $dl . '</span>';
            $this->toolbar->addText($dl);
        }

        $form = $this->initSubmissionForm();
        $this->tpl->setContent($form->getHTML());
        $this->tpl->printToStdout();
        exit;
    }

    protected function confirmDeleteSubmission()
    {
        $this->handleSubmissionTabs($this->tabs);

        if (!$this->submission->canSubmit()) {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt("exercise_time_over"));
            return;
        }

        $gui = new ilConfirmationGUI();
        
        $this->ctrl->setParameterByClass(get_class($this), 'ass_id', $this->assignment->getId());
        $gui->setFormAction($this->ctrl->getFormActionByClass(get_class($this)));
        
        $gui->setHeaderText($this->plugin->txt('confirm_delete_submission'));
        $gui->setConfirm($this->plugin->txt('delete_submission'), 'deleteSubmission');
        $gui->setCancel($this->lng->txt('cancel'), 'submissionScreen');
        
        $this->tpl->setContent($gui->getHTML());
        $this->tpl->printToStdout();
        exit;
    }

    protected function deleteSubmission()
    {
        if (!$this->submission->canSubmit()) {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt("exercise_time_over"));
        } else {
            $this->submission->deleteAllFiles();
            $task = ilExAutoScoreTask::getSubmissionTask($this->submission);
            $task->clearSubmissionData();
            $task->save();
            $task->updateMemberStatus();
        }

        $this->tpl->setOnScreenMessage('success', $this->plugin->txt('submission_deleted'), true);
        $this->returnToParent();
    }

    protected function initSubmissionForm(): ilPropertyFormGUI
    {
        $existing = [];
        foreach ($this->submission->getFiles() as $file) {
            $existing[$file["filetitle"]] = $file;
        }

        $requiredFiles = ilExAutoScoreRequiredFile::getForAssignment($this->assignment->getId());

        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt('required_files'));
        $this->ctrl->setParameterByClass(get_class($this), 'ass_id', $this->assignment->getId());
        $form->setFormAction($this->ctrl->getFormAction($this));
        
        $form->addCommandButton('uploadSubmission', $this->lng->txt('upload'));

        foreach ($requiredFiles as $file) {
            $fileUpload = new ilFileInputGUI($file->getFilename(), 'exautoscore_file_upload_' . $file->getId());
            $info = [];
            
            if (!empty($file->getMaxSize())) {
                $info[] = '<p>' . sprintf($this->plugin->txt('required_max_size_info'), ceil($file->getMaxSize() / 1000)) . '</p>';
            }
            if (!empty($file->getRequiredEncoding())) {
                $info[] = '<p>' . sprintf($this->plugin->txt('required_encoding_info'), $file->getRequiredEncoding()) . '</p>';
            }
            if (!empty($file->getDescription())) {
                $info[] = '<p>' . $file->getDescription() . '</p>';
            }
            
            if (!isset($existing[$file->getFilename()])) {
                $fileUpload->setRequired(true);
            } else {
                $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'delivered', $existing[$file->getFilename()]['returned_id']);
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

        if (!$form->checkInput()) {
            $this->tpl->setContent($form->getHTML());
            $this->tpl->printToStdout();
            exit;
        }

        $upload = $DIC->upload();
        if (!$upload->hasBeenProcessed()) {
            $upload->process();
        }

        $results = [];
        foreach ($upload->getResults() as $result) {
            $results[$result->getName()] = $result;
        }

        // Validierung
        $errors = false;
        foreach ($requiredFiles as $required) {
            $item = $form->getItemByPostVar('exautoscore_file_upload_' . $required->getId());
            $uploadedFile = $results[$required->getFilename()] ?? null;

            if ($uploadedFile === null) {
                if ($item->getRequired()) {
                    $item->setAlert($this->plugin->txt('upload_error_filename'));
                    $errors = true;
                }
                continue;
            }

            if (!$uploadedFile->isOK()) {
                $item->setAlert($this->plugin->txt('upload_error_file'));
                $errors = true;
            } elseif (!empty($required->getMaxSize()) && $uploadedFile->getSize() > $required->getMaxSize()) {
                $item->setAlert($this->plugin->txt('upload_error_max_size'));
                $errors = true;
            } elseif (!empty($required->getRequiredEncoding())) {
                $data = file_get_contents($uploadedFile->getPath());
                if (!mb_check_encoding($data, $required->getRequiredEncoding())) {
                    $item->setAlert($this->plugin->txt('upload_error_encoding'));
                    $errors = true;
                }
            }
        }
        
        if ($errors) {
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt('upload_error_file'));
            $this->tpl->setContent($form->getHTML());
            $this->tpl->printToStdout();
            exit;
        }

        // Dateien sammeln
        $existing = [];
        foreach ($this->submission->getFiles() as $file) {
            $existing[$file["filetitle"]][] = $file['returned_id'];
        }
        
        $required = [];
        $new = [];
        $failed = null;
        $uploaded_ids = [];

        foreach ($requiredFiles as $requiredFile) {
            $required[$requiredFile->getFilename()] = true;

            if (isset($results[$requiredFile->getFilename()])) {
                $result = $results[$requiredFile->getFilename()];

                $uploadData = [
                    'name'     => $result->getName(),
                    'size'     => $result->getSize(),
                    'tmp_name' => $result->getPath()
                ];

                if ($this->submission->uploadFile($uploadData)) {
                    $new[] = $requiredFile;
                    
                    $newFiles = $this->submission->getFiles();
                    foreach ($newFiles as $newFile) {
                        if ($newFile['filetitle'] == $result->getName()) {
                            $returned_id = $newFile['returned_id'];
                            $uploaded_ids[] = $returned_id;
                            
                            // FIX: Konvertiere absoluten Pfad in relativen Pfad wie Standard-ILIAS
                            $db = $DIC->database();
                            $absolute_path = $newFile['filename'];
                            
                            // Entferne CLIENT_DATA_DIR Prefix um relativen Pfad zu erhalten
                            $relative_path = str_replace(CLIENT_DATA_DIR . '/', '', $absolute_path);
                            
                            $db->update('exc_returned',
                                ['filename' => ['text', $relative_path]],
                                ['returned_id' => ['integer', $returned_id]]
                            );
                            
                            $DIC->logger()->root()->error('ExAutoScore: Fixed filename path for returned_id=' . $returned_id . ', relative_path=' . $relative_path);
                            
                            break;
                        }
                    }
                } else {
                    $failed = $requiredFile;
                    break;
                }
            }
        }

        if (isset($failed)) {
            foreach ($this->submission->getFiles() as $file) {
                if (!is_array($existing[$file["filetitle"]])
                    || !in_array($file['returned_id'], $existing[$file["filetitle"]])) {
                    $this->submission->deleteSelectedFiles([$file['returned_id']]);
                }
            }

            $this->tpl->setOnScreenMessage('failure', sprintf($this->plugin->txt("submission_upload_error"), $failed->getFilename()));
            $this->tpl->setContent($form->getHTML());
            $this->tpl->printToStdout();
            exit;
        }

        if (empty($new)) {
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("submission_no_upload"));
            $this->tpl->setContent($form->getHTML());
            $this->tpl->printToStdout();
            exit;
        }

        // Cleanup und Team-ID setzen
        if (!empty($new)) {
            foreach ($new as $requiredFile) {
                $filename = $requiredFile->getFilename();
                if (isset($existing[$filename]) && is_array($existing[$filename])) {
                    $this->submission->deleteSelectedFiles($existing[$filename]);
                }
            }
            
            foreach ($existing as $filename => $returned_ids) {
                if (!isset($required[$filename])) {
                    $this->submission->deleteSelectedFiles($returned_ids);
                }
            }

            if ($this->submission->getTeam() && !empty($uploaded_ids)) {
                $team_id = $this->submission->getTeam()->getId();
                $db = $DIC->database();
                
                foreach ($uploaded_ids as $returned_id) {
                    $db->update('exc_returned',
                        ['team_id' => ['integer', $team_id]],
                        ['returned_id' => ['integer', $returned_id]]
                    );
                }
            }

            $task = ilExAutoScoreTask::getSubmissionTask($this->submission);
            $task->clearSubmissionData();
            $task->save();
            $task->updateMemberStatus();
        }

        $this->sendSubmission();
    }

    protected function sendSubmission()
    {
        require_once (__DIR__ . '/class.ilExAutoScoreConnector.php');
        $connector = new ilExAutoScoreConnector();
        
        if ($connector->sendSubmission($this->submission, $this->user)) {
            $this->tpl->setOnScreenMessage('success', $this->plugin->txt("submission_success"), true);
        } else {
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("submission_error"), true);
        }

        $this->returnToParent();
    }    

protected function downloadSubmittedFile()
    {
        global $DIC;
        
        $delivered_id = (int) $_REQUEST["delivered"];

        if (!isset($this->submission) || !$this->submission->canView()) {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt("access_denied"), true);
            $this->returnToParent();
            return;
        }

        if (!is_array($delivered_id) && $delivered_id > 0) {
            $delivered_id = [$delivered_id];
        }
        
        if (count($delivered_id) > 0) {
            try {
                $this->submission->downloadFiles($delivered_id);
                exit;
            } catch (Exception $e) {
                #$DIC->logger()->root()->error('ExAutoScore: Submitted file download failed - ' . $e->getMessage());
                
                $plugin = ilExAutoScorePlugin::getInstance();
                if ($plugin->getConfig()->get('enable_debug_logs') && $plugin->hasAdminAccess()) {
                    $error_msg = sprintf(
                        $this->plugin->txt('submitted_file_not_found_debug'),
                        $delivered_id[0],
                        $e->getMessage()
                    );
                } else {
                    $error_msg = $this->plugin->txt('file_not_found');
                }
                
                $this->tpl->setOnScreenMessage('failure', $error_msg, true);
                $this->returnToParent();
            }
        } else {
            $this->returnToParent();
        }
    }

    protected function downloadProvidedFile()
    {
        global $DIC;
        
        $file = ilExAutoScoreProvidedFile::findOrGetInstance($_REQUEST['file_id']);
        if ($file->getAssignmentId() != $this->assignment->getId()) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            $this->returnToParent();
            return;
        }

        if (isset($this->submission)) {
            $state = ilExcAssMemberState::getInstanceByIds($this->assignment->getId(), $this->user->getId());

            if (!$state->areInstructionsVisible() || !$file->isPublic() || $file->getAssignmentId() != $this->assignment->getId()) {
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
                $this->returnToParent();
                return;
            }
        }

        try {
            $file->downloadFile();
        } catch (Exception $e) {
            #$DIC->logger()->root()->error('ExAutoScore: File download failed - ' . $e->getMessage());
            
            $plugin = ilExAutoScorePlugin::getInstance();
            if ($plugin->getConfig()->get('enable_debug_logs') && $plugin->hasAdminAccess()) {
                $error_msg = sprintf(
                    $this->plugin->txt('file_not_found_debug'),
                    $file->getFilename(),
                    $file->getId(),
                    $e->getMessage()
                );
            } else {
                $error_msg = $this->plugin->txt('file_not_found');
            }
            
            $this->tpl->setOnScreenMessage('failure', $error_msg, true);
            $this->returnToParent();
        }
    }

    protected function downloadExampleFile()
    {
        global $DIC;
        
        $file = ilExAutoScoreRequiredFile::findOrGetInstance($_REQUEST['file_id']);
        if ($file->getAssignmentId() != $this->assignment->getId()) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            $this->returnToParent();
            return;
        }

        if (isset($this->submission)) {
            $access = false;
            if ($file->getAssignmentId() != $this->assignment->getId()) {
                $access = false;
            } elseif ($this->assignment->getFeedbackDate() == ilExAssignment::FEEDBACK_DATE_DEADLINE) {
                $state = ilExcAssMemberState::getInstanceByIds($this->assignment->getId(), $this->user->getId());
                $access = $state->hasSubmissionEndedForAllUsers();
            } elseif ($this->assignment->getFeedbackDate() == ilExAssignment::FEEDBACK_DATE_CUSTOM) {
                $access = $this->assignment->afterCustomDate();
            } else {
                $access = $this->submission->hasSubmitted();
            }

            if (!$access) {
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
                $this->returnToParent();
                return;
            }
        }

        try {
            $file->downloadFile();
        } catch (Exception $e) {
            #$DIC->logger()->root()->error('ExAutoScore: File download failed - ' . $e->getMessage());
            
            $plugin = ilExAutoScorePlugin::getInstance();
            if ($plugin->getConfig()->get('enable_debug_logs') && $plugin->hasAdminAccess()) {
                $error_msg = sprintf(
                    $this->plugin->txt('file_not_found_debug'),
                    $file->getFilename(),
                    $file->getId(),
                    $e->getMessage()
                );
            } else {
                $error_msg = $this->plugin->txt('file_not_found');
            }
            
            $this->tpl->setOnScreenMessage('failure', $error_msg, true);
            $this->returnToParent();
        }
    }

    protected function handleSubmissionTabs(ilTabsGUI $tabs)
    {
        $tabs->clearTargets();
        
        $tabs->setBackTarget(
            $this->lng->txt("back"),
            $this->ctrl->getLinkTargetByClass(
                [ilAssignmentPresentationGUI::class],
                ''
            )
        );

        $this->ctrl->setParameterByClass(get_class($this), 'ass_id', $this->assignment->getId());
        $tabs->addTab(
            "submission",
            $this->lng->txt("exc_submission"),
            $this->ctrl->getLinkTargetByClass(
                [ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, get_class($this)],
                'submissionScreen'
            )
        );
        
        $tabs->activateTab("submission");

        if ($this->assignment->hasTeam()) {
            ilExSubmissionTeamGUI::handleTabs();
        }
    }

    public function modifySubmissionTableActions(ilExSubmission $a_submission, &$a_actions): void
    {
        global $DIC;

        $task = ilExAutoScoreTask::getSubmissionTask($a_submission);
        if (!empty($task->getProtectedFeedbackHtml())) {

            $factory = $DIC->ui()->factory();
            $renderer = $DIC->ui()->renderer();

            // Feedback HTML vorbereiten
            // WICHTIG: stdout/stderr IMMER entfernen (gehört ins Debug-Protokoll)
            $feedbackHtml = $task->getProtectedFeedbackHtml();
            $feedbackHtml = preg_replace('/<details[^>]*>.*?<\/details>/is', '', $feedbackHtml);

            // Modal erstellen
            $page = $factory->modal()->lightboxTextPage(
                ilUtil::stripScriptHTML($feedbackHtml, $this->plugin->getAllowedTags()),
                $this->plugin->txt('protected_feedback_html')
            );
            $modal = $factory->modal()->lightbox([$page]);

            // Modal-HTML ins DOM einfügen (per JavaScript)
            $DIC->ui()->mainTemplate()->addOnLoadCode(
                "document.body.insertAdjacentHTML('beforeend', " . 
                json_encode($renderer->render($modal)) . 
                ");"
            );

            // Button als UI-Component
            $button = $factory->button()->shy(
                $this->plugin->txt("protected_feedback_html"), 
                ''
            )->withOnClick($modal->getShowSignal());

            $a_actions[] = $button;
        }
    }

    protected function returnToParent() {
        $this->ctrl->returnToParent($this);
    }

    public function buildSubmissionPropertiesAndActions(
        PropertyAndActionBuilderUI $builder
    ): void {
        global $DIC;
        
        $lng  = $DIC->language();
        $ctrl = $DIC->ctrl();
        $f    = $DIC->ui()->factory();
        $r    = $DIC->ui()->renderer();
        $sub  = $this->getSubmission();
        
        if (!$sub) {
            return;
        }
        
        if ($sub->getAssignment()->hasTeam() && $sub->hasNoTeamYet()) {
            $ctrl->setParameterByClass(ilExSubmissionTeamGUI::class, 'ass_id', $sub->getAssignment()->getId());
            $url = $ctrl->getLinkTargetByClass(
                [ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, ilExSubmissionTeamGUI::class],
                'createSingleMemberTeam'
            );
            
            $builder->setMainAction($builder::SEC_SUBMISSION, $f->button()->primary($lng->txt('exc_create_team'), $url));
            $builder->addView('submission', $lng->txt('exc_submission'), $url);
            
            return;
        }

        // Prüfe ob Docker-Build fehlgeschlagen ist und verstecke submit-buttons
        $exampleTask = ilExAutoScoreTask::getExampleTask($sub->getAssignment()->getId());
        $hasDebugError = !empty($exampleTask->getDebugLogs());

        if ($hasDebugError) {   
            
            if ($this->plugin->canDefine()) {
                $ctrl->setParameterByClass(strtolower(get_class($this)), 'ass_id', $sub->getAssignment()->getId());
                $url = $ctrl->getLinkTargetByClass(
                    [ ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, strtolower(get_class($this)) ],
                    'submissionScreen');
                $builder->addView('submission', $this->plugin->txt('view_details'), $url);
            }
            
            return;
        }  
        
        // ./
        
        $gui_class = $sub->getAssignment()->getAssignmentType()->usesTeams()
            ? ilExAssTypeAutoScoreTeamGUI::class
            : ilExAssTypeAutoScoreUserGUI::class;
        
        $ass       = $sub->getAssignment();
        $files_cnt = count($sub->getFiles());
        $can_submit = $sub->canSubmit();

        if ($can_submit) {
            $ctrl->setParameterByClass($gui_class, 'ass_id', $ass->getId());
            $ctrl->setParameterByClass(ilExSubmissionGUI::class, 'ass_id', $ass->getId());

            $url = $ctrl->getLinkTargetByClass(
                [ ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, $gui_class ],
                ''
            );

            $title = ($files_cnt ? $lng->txt('exc_edit_submission') : $lng->txt('exc_hand_in'));
            $builder->setMainAction($builder::SEC_SUBMISSION, $f->button()->primary($title, $url));
            $builder->addView('submission', $lng->txt('exc_submission'), $url);

        } else {
            if ($files_cnt > 0) {
                $ctrl->setParameterByClass($gui_class, 'ass_id', $ass->getId());
                $ctrl->setParameterByClass(ilExSubmissionGUI::class, 'ass_id', $ass->getId());

                $url_self = $ctrl->getLinkTargetByClass(
                    [ ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, strtolower(get_class($this)) ],
                    'viewSubmission'
                );

                $builder->addAction($builder::SEC_SUBMISSION, $f->link()->standard($lng->txt('already_delivered_files'), $url_self));
                $builder->addView('submission', $lng->txt('exc_submission'), $url_self);
            }
        }
        
        require_once __DIR__ . '/models/class.ilExAutoScoreTask.php';
        $task = \ilExAutoScoreTask::getSubmissionTask($sub);
        
        if ($task && $task->getReturnPoints() !== null) {
            $builder->addProperty($builder::SEC_SUBMISSION, $this->plugin->txt('return_points'), (string) $task->getReturnPoints());
        }
        
        if ($task && $task->getInstantStatus()) {
            $builder->addProperty(
                $builder::SEC_SUBMISSION, 
                $this->plugin->txt('instant_status'), 
                $this->getStyledStatusSymbol($task->getInstantStatus())
            );
        }

        // Nach dem instant_status Block hinzufügen:
        if ($task && $task->getInstantMessage()) {
            $builder->addProperty(
                $builder::SEC_SUBMISSION, 
                $this->plugin->txt('instant_message'), 
                $this->formatInstantMessage($task->getInstantMessage())
            );
        }        
        
        if ($task && $task->getProtectedFeedbackHtml() && $this->canShowExtendedFeedbackByDeadline($ass, $sub)) {
            try {
                $item_id = "exautoscore_feedback_modal_" . $task->getId();
                
                $modal = ilModalGUI::getInstance();
                $modal->setId($item_id);
                $modal->setType(ilModalGUI::TYPE_LARGE);
                
                $feedbackHtml = $task->getProtectedFeedbackHtml();
                
                $feedbackHtml = preg_replace('/<details[^>]*>.*?<\/details>/is', '', $feedbackHtml);
                
                $modal->setBody(ilUtil::stripScriptHTML($feedbackHtml, $this->plugin->getAllowedTags()));
                $modal->setHeading($this->plugin->txt('protected_feedback_html'));
                
                $modal_html = $modal->getHTML();
                
                $button_html = sprintf(
                    '<button type="button" class="btn btn-default" onclick="$(\'#%s\').modal(\'show\'); return false;">%s</button>',
                    $item_id,
                    $this->plugin->txt('show_extended_feedback')
                );
                
                $combined_html = $modal_html . $button_html;
                
                $builder->addProperty(
                    $builder::SEC_SUBMISSION, 
                    '',
                    $combined_html
                );
                
            } catch (Exception $e) {
                #$DIC->logger()->root()->error('ExAutoScore: Error adding feedback: ' . $e->getMessage());
            }
        }
    }

    // 2. Vereinfache viewSubmission() - nur Dateiliste, keine Auswertung:

    protected function viewSubmission()
    {
        global $DIC;

        $this->handleSubmissionTabs($this->tabs);

        if (!isset($this->submission) || !$this->submission->canView()) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            $this->returnToParent();
            return;
        }

        $files = $this->submission->getFiles();

        // NUR DATEILISTE - KEINE AUSWERTUNG
        $files_html = '';
        if ($files && count($files)) {
            $files_html .= '<div class="ilBox"><h3>' . $this->lng->txt('exc_files_returned') . '</h3><ul>';
            foreach ($files as $file) {
                $this->ctrl->setParameterByClass(get_class($this), 'delivered', $file['returned_id']);
                $dl = $this->ctrl->getLinkTargetByClass(
                    [ ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, get_class($this) ],
                    'downloadSubmittedFile'
                );
                $files_html .= '<li><a href="' . $dl . '">' . htmlspecialchars($file['filetitle']) . '</a></li>';
            }
            $files_html .= '</ul></div>';
        } else {
            $files_html .= '<div class="ilBox"><em>' . $this->lng->txt('no_items') . '</em></div>';
        }

        $this->tpl->setContent($files_html);
        $this->tpl->printToStdout();
        exit;
    }
}    