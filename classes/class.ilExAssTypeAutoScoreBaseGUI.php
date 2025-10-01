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

    public function executeCommand(): void
    {
        global $DIC;
        
        // DEBUG-Logging
        $DIC->logger()->root()->error("ExAutoScore executeCommand ENTRY", [
            'current_gui' => get_class($this),
            'next_class' => $this->ctrl->getNextClass($this),
            'cmd' => $this->ctrl->getCmd(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ]);
        
        $DIC->logger()->root()->error("ExAutoScore DEBUG: current GUI = " . get_class($this));
 
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

        $DIC->logger()->root()->error('ExAutoScore executeCommand', [
            'next_class' => $next_class, 
            'cmd' => $cmd,
            'gui_class' => get_class($this)
        ]);

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
                        $DIC->logger()->root()->warning('ExAutoScore: Unknown command: ' . $cmd);
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
        }

        elseif ($a_submission->canSubmit()) {

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
                $contents[] = '<span class="ilTag">' . ilUtil::prepareFormOutput($task->getInstantStatus()) . '</span>';
            }
            if (!empty($task->getInstantMessage())) {
                $contents[] = '<pre>' . ilUtil::prepareFormOutput($task->getInstantMessage()) . '</pre>';
            }
            if (!empty($contents)) {
                $a_info->addProperty($this->plugin->txt("instant_message"), implode('<br />', $contents));
            }

        } elseif (!empty($task->getSubmitTime())) {
            $time = new ilDateTime($task->getSubmitTime(), IL_CAL_DATETIME);
            $a_info->addProperty($this->plugin->txt("submit_time"), ilDatePresentation::formatDate($time));
            $a_info->addProperty($this->plugin->txt("submit_message"), $task->getSubmitMessage());
        }
    }

    public function getOverviewAdditionalFeedback(ilInfoScreenGUI $a_info, ilExSubmission $a_submission): void
    {
        $task = ilExAutoScoreTask::getSubmissionTask($a_submission);

        if (!empty($task->getProtectedFeedbackHtml())) {
            $item_id = "exautoscore_feedback_html_" . $a_submission->getAssignment()->getId();

            $modal = ilModalGUI::getInstance();
            $modal->setId($item_id);
            $modal->setType(ilModalGUI::TYPE_LARGE);
            $modal->setBody(ilUtil::stripScriptHTML($task->getProtectedFeedbackHtml(), $this->plugin->getAllowedTags()));
            $modal->setHeading($this->plugin->txt('protected_feedback_html'));

            $button_html = sprintf(
                '<button type="button" class="btn btn-default" onclick="$(\'#%s\').modal(\'show\');">%s</button>',
                $item_id,
                $this->plugin->txt('show_extended_feedback')
            );

            $a_info->addProperty('', $modal->getHTML() . $button_html);
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
        
        // WICHTIG: Assignment ID aus verschiedenen Quellen ermitteln
        $ass_id = null;
        
        if (isset($this->assignment)) {
            $ass_id = $this->assignment->getId();
        } elseif (isset($this->submission)) {
            $ass_id = $this->submission->getAssignment()->getId();
        } elseif (isset($_GET['ass_id'])) {
            $ass_id = (int) $_GET['ass_id'];
        }
        
        if (empty($ass_id)) {
            $DIC->logger()->root()->error('ExAutoScore: Cannot determine assignment ID in getSubmissionScreenLinkTarget', [
                'has_assignment' => isset($this->assignment),
                'has_submission' => isset($this->submission),
                'get_ass_id' => $_GET['ass_id'] ?? 'not set'
            ]);
            // Fallback: versuche aus URL zu lesen
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
        $DIC->logger()->root()->error('ExAutoScore submissionScreen CALLED');

        $this->handleSubmissionTabs($this->tabs);

        if (!isset($this->submission)) {
            $DIC->logger()->root()->error('ExAutoScore: NO SUBMISSION OBJECT!');
            $this->tpl->setOnScreenMessage('failure', 'Interner Fehler: Keine Abgabe gefunden');
            return;
        }

        $canSubmit = $this->submission->canSubmit();
        $canAddFile = $this->submission->canAddFile();
        
        $DIC->logger()->root()->error('ExAutoScore: checking permissions', [
            'canSubmit' => $canSubmit,
            'canAddFile' => $canAddFile
        ]);

        if (!$canSubmit) {
            $DIC->logger()->root()->error('ExAutoScore: canSubmit is FALSE');
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

        if (!$canAddFile) {
            $DIC->logger()->root()->error('ExAutoScore: canAddFile is FALSE - EXITING METHOD');
            $this->tpl->setOnScreenMessage('info', 'Sie können derzeit keine Dateien hochladen.');
            return;
        }
        
        $DIC->logger()->root()->error('ExAutoScore: preparing form');
        
        $deadline = $this->assignment->getPersonalDeadline($this->user->getId());
        if ($deadline && time() > $deadline) {
            $dl = ilDatePresentation::formatDate(new ilDateTime($deadline, IL_CAL_UNIX));
            $dl = sprintf($this->lng->txt("exc_late_submission_warning"), $dl);
            $dl = '<span class="warning">' . $dl . '</span>';
            $this->toolbar->addText($dl);
        }

        $DIC->logger()->root()->error('ExAutoScore: calling initSubmissionForm');
        $form = $this->initSubmissionForm();
        
        $DIC->logger()->root()->error('ExAutoScore: getting form HTML');
        $html = $form->getHTML();
        
        $DIC->logger()->root()->error('ExAutoScore: setting content', [
            'html_length' => strlen($html)
        ]);
        
        $this->tpl->setContent($html);
        
        $DIC->logger()->root()->error('ExAutoScore: printing template NOW');
        $this->tpl->printToStdout();
        
        $DIC->logger()->root()->error('ExAutoScore: after printToStdout - calling exit');
        exit;
    }

    protected function confirmDeleteSubmission()
    {
        global $DIC;
        
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
        
        $DIC->logger()->root()->error('ExAutoScore confirmDelete: printing template NOW');
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

        $DIC->logger()->root()->error('ExAutoScore uploadSubmission CALLED');
        
        if ($this->submission->getTeam()) {
            $DIC->logger()->root()->error('ExAutoScore: Team exists, ID = ' . $this->submission->getTeam()->getId());
        } else {
            $DIC->logger()->root()->error('ExAutoScore: NO TEAM!');
        }

        $this->handleSubmissionTabs($this->tabs);

        $requiredFiles = ilExAutoScoreRequiredFile::getForAssignment($this->assignment->getId());

        $form = $this->initSubmissionForm();
        $form->setValuesByPost();

        if (!$form->checkInput()) {
            $this->tpl->setContent($form->getHTML());
            $DIC->logger()->root()->error('ExAutoScore: form validation failed, printing template');
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

        $DIC->logger()->root()->error('ExAutoScore: Uploaded files: ' . implode(', ', array_keys($results)));
        
        $expectedNames = [];
        foreach ($requiredFiles as $required) {
            $expectedNames[] = $required->getFilename();
        }
        $DIC->logger()->root()->error('ExAutoScore: Expected files: ' . implode(', ', $expectedNames));

        $errors = false;
        foreach ($requiredFiles as $required) {
            /** @var ilFormPropertyGUI $item */
            $item = $form->getItemByPostVar('exautoscore_file_upload_' . $required->getId());

            $uploadedFile = null;
            if (isset($results[$required->getFilename()])) {
                $uploadedFile = $results[$required->getFilename()];
            }

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
            $DIC->logger()->root()->error('ExAutoScore: upload errors, printing template');
            $this->tpl->printToStdout();
            exit;
        }

        $existing = [];
        foreach ($this->submission->getFiles() as $file) {
            $existing[$file["filetitle"]][] = $file['returned_id'];
        }
        
        $required = [];
        $new = [];
        $failed = null;
        $uploaded_ids = []; // NEU: Sammle die IDs der neu hochgeladenen Dateien

        foreach ($requiredFiles as $requiredFile) {
            $required[$requiredFile->getFilename()] = true;

            if (isset($results[$requiredFile->getFilename()])) {
                $result = $results[$requiredFile->getFilename()];

                $uploadData = [
                    'name'     => $result->getName(),
                    'size'     => $result->getSize(),
                    'tmp_name' => $result->getPath()
                ];
                
                $DIC->logger()->root()->error('ExAutoScore: Uploading file with team_id = ' . 
                    ($this->submission->getTeam() ? $this->submission->getTeam()->getId() : 'NULL'));

                if ($this->submission->uploadFile($uploadData)) {
                    $new[] = $requiredFile;
                    
                    // NEU: Hole die ID der gerade hochgeladenen Datei
                    $newFiles = $this->submission->getFiles();
                    foreach ($newFiles as $newFile) {
                        if ($newFile['filetitle'] == $result->getName()) {
                            $uploaded_ids[] = $newFile['returned_id'];
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
            $DIC->logger()->root()->error('ExAutoScore: file upload failed, printing template');
            $this->tpl->printToStdout();
            exit;
        }

        if (empty($new)) {
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("submission_no_upload"));
            $this->tpl->setContent($form->getHTML());
            $DIC->logger()->root()->error('ExAutoScore: no new files, printing template');
            $this->tpl->printToStdout();
            exit;
        }

        if (!empty($new)) {
            // Lösche alte Versionen der neu hochgeladenen Dateien
            foreach ($new as $requiredFile) {
                $filename = $requiredFile->getFilename();
                if (isset($existing[$filename]) && is_array($existing[$filename])) {
                    $this->submission->deleteSelectedFiles($existing[$filename]);
                }
            }
            
            // Lösche Dateien, die nicht mehr gefordert sind
            foreach ($existing as $filename => $returned_ids) {
                if (!isset($required[$filename])) {
                    $this->submission->deleteSelectedFiles($returned_ids);
                }
            }

            // NEU: WICHTIGER FIX - Setze team_id für die neu hochgeladenen Dateien
            if ($this->submission->getTeam() && !empty($uploaded_ids)) {
                $team_id = $this->submission->getTeam()->getId();
                $db = $DIC->database();
                
                foreach ($uploaded_ids as $returned_id) {
                    $db->update('exc_returned',
                        ['team_id' => ['integer', $team_id]],
                        ['returned_id' => ['integer', $returned_id]]
                    );
                    $DIC->logger()->root()->error('ExAutoScore: Updated team_id for returned_id ' . $returned_id . ' to ' . $team_id);
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
        global $DIC;
        $DIC->logger()->root()->error('ExAutoScore sendSubmission CALLED');

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
        $delivered_id = (int) $_REQUEST["delivered"];

        if (!isset($this->submission) || !$this->submission->canView()) {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt("access_denied"), true);
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

    protected function downloadProvidedFile()
    {
        $file = ilExAutoScoreProvidedFile::findOrGetInstance($_REQUEST['file_id']);
        if ($file->getAssignmentId() != $this->assignment->getId()) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            $this->returnToParent();
        }

        if (isset($this->submission)) {
            $state = ilExcAssMemberState::getInstanceByIds($this->assignment->getId(), $this->user->getId());

            if (!$state->areInstructionsVisible() || !$file->isPublic() || $file->getAssignmentId() != $this->assignment->getId()) {
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
                $this->returnToParent();
            }
        }

        $file->downloadFile();
    }

    protected function downloadExampleFile()
    {
        $file = ilExAutoScoreRequiredFile::findOrGetInstance($_REQUEST['file_id']);
        if ($file->getAssignmentId() != $this->assignment->getId()) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            $this->returnToParent();
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

        $file->downloadFile();
    }

    protected function handleSubmissionTabs(ilTabsGUI $tabs)
    {
        global $DIC;
        $DIC->logger()->root()->error('ExAutoScore handleSubmissionTabs CALLED');
        
        $tabs->clearTargets();
        $DIC->logger()->root()->error('ExAutoScore: tabs cleared');
        
        $tabs->setBackTarget(
            $this->lng->txt("back"),
            $this->ctrl->getLinkTargetByClass(
                [ilAssignmentPresentationGUI::class],
                ''
            )
        );

        $DIC->logger()->root()->error('ExAutoScore: back target set');

        $this->ctrl->setParameterByClass(get_class($this), 'ass_id', $this->assignment->getId());
        $tabs->addTab(
            "submission",
            $this->lng->txt("exc_submission"),
            $this->ctrl->getLinkTargetByClass(
                [ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, get_class($this)],
                'submissionScreen'
            )
        );
        
        $DIC->logger()->root()->error('ExAutoScore: submission tab added');
        
        $tabs->activateTab("submission");
        
        $DIC->logger()->root()->error('ExAutoScore: submission tab activated');

        if ($this->assignment->hasTeam()) {
            $DIC->logger()->root()->error('ExAutoScore: has team, calling handleTabs');
            ilExSubmissionTeamGUI::handleTabs();
        }
        
        $DIC->logger()->root()->error('ExAutoScore handleSubmissionTabs FINISHED');
    }

    public function modifySubmissionTableActions(ilExSubmission $a_submission, &$a_actions): void
    {
        global $DIC;

        $task = ilExAutoScoreTask::getSubmissionTask($a_submission);
        if (!empty($task->getProtectedFeedbackHtml())) {

            $factory = $DIC->ui()->factory();
            $renderer = $DIC->ui()->renderer();

            $page = $factory->modal()->lightboxTextPage(
                ilUtil::stripScriptHTML($task->getProtectedFeedbackHtml(), $this->plugin->getAllowedTags()),
                $this->plugin->txt('protected_feedback_html')
            );
            $modal = $factory->modal()->lightbox([$page]);

            $this->tpl->addLightbox($renderer->render($modal), 'exautoscore_lightbox_' . $task->getId());

            $a_actions[] = $factory->button()->shy($this->plugin->txt("protected_feedback_html"), '')
                ->withOnClick($modal->getShowSignal());
        }
    }

    protected function returnToParent() {
        $this->ctrl->returnToParent($this);
    }

    public function buildSubmissionPropertiesAndActions(
        PropertyAndActionBuilderUI $builder
    ): void {
        global $DIC;
        
        $DIC->logger()->root()->error('ExAutoScore buildSubmissionPropertiesAndActions CALLED');
        
        $lng  = $DIC->language();
        $ctrl = $DIC->ctrl();
        $f    = $DIC->ui()->factory();
        $r    = $DIC->ui()->renderer();
        $sub  = $this->getSubmission();
        
        if (!$sub) {
            $DIC->logger()->root()->error('ExAutoScore: NO SUBMISSION in buildSubmissionPropertiesAndActions');
            return;
        }
        
        $DIC->logger()->root()->error('ExAutoScore: Submission ID = ' . $sub->getAssignment()->getId());
        
        // WICHTIG: Prüfe ob Team-Aufgabe und ob Team noch nicht erstellt
        if ($sub->getAssignment()->hasTeam() && $sub->hasNoTeamYet()) {
            $DIC->logger()->root()->error('ExAutoScore: hasNoTeamYet - showing team creation button');
            
            // Zeige "Team erstellen" Button
            $ctrl->setParameterByClass(ilExSubmissionTeamGUI::class, 'ass_id', $sub->getAssignment()->getId());
            $url = $ctrl->getLinkTargetByClass(
                [ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, ilExSubmissionTeamGUI::class],
                'createSingleMemberTeam'
            );
            
            $builder->setMainAction($builder::SEC_SUBMISSION, $f->button()->primary($lng->txt('exc_create_team'), $url));
            $builder->addView('submission', $lng->txt('exc_submission'), $url);
            
            return;
        }
        
        $gui_class = $sub->getAssignment()->getAssignmentType()->usesTeams()
            ? ilExAssTypeAutoScoreTeamGUI::class
            : ilExAssTypeAutoScoreUserGUI::class;
        
        $DIC->logger()->root()->error('ExAutoScore: Using GUI class = ' . $gui_class);
        
        if ($sub->canSubmit()) {
            $title = ($sub->getFiles() ? $lng->txt('exc_edit_submission') : $lng->txt('exc_hand_in'));
            
            $ctrl->setParameterByClass($gui_class, 'ass_id', $sub->getAssignment()->getId());
            $ctrl->setParameterByClass(ilExSubmissionGUI::class, 'ass_id', $sub->getAssignment()->getId());
            
            $url = $ctrl->getLinkTargetByClass(
                [ ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, $gui_class ],
                '');              
            
            $DIC->logger()->root()->error('ExAutoScore: Setting main action with URL = ' . $url);
            
            $builder->setMainAction($builder::SEC_SUBMISSION, $f->button()->primary($title, $url));
            $builder->addView('submission', $lng->txt('exc_submission'), $url);
        } else {
            if (count($sub->getFiles()) > 0) {
                $ctrl->setParameterByClass($gui_class, 'ass_id', $sub->getAssignment()->getId());
                $ctrl->setParameterByClass(ilExSubmissionGUI::class, 'ass_id', $sub->getAssignment()->getId());
                
                $url = $ctrl->getLinkTargetByClass(
                    [ ilAssignmentPresentationGUI::class, ilExSubmissionGUI::class, $gui_class ],
                    'submissionScreen'
                );
                $builder->addAction($builder::SEC_SUBMISSION, $f->link()->standard($lng->txt('already_delivered_files'), $url));
                $builder->addView('submission', $lng->txt('exc_submission'), $url);
            }
        }
        
        require_once __DIR__ . '/models/class.ilExAutoScoreTask.php';
        $task = \ilExAutoScoreTask::getSubmissionTask($sub);
        
        $DIC->logger()->root()->error('ExAutoScore: Task loaded, ID = ' . ($task ? $task->getId() : 'NULL'));
        
        if ($task && $task->getReturnPoints() !== null) {
            $DIC->logger()->root()->error('ExAutoScore: Adding return_points = ' . $task->getReturnPoints());
            $builder->addProperty($builder::SEC_SUBMISSION, $this->plugin->txt('return_points'), (string) $task->getReturnPoints());
        } else {
            $DIC->logger()->root()->error('ExAutoScore: NO return_points available');
        }
        
        if ($task && $task->getInstantStatus()) {
            $DIC->logger()->root()->error('ExAutoScore: Adding instant_status = ' . $task->getInstantStatus());
            $builder->addProperty($builder::SEC_SUBMISSION, $this->plugin->txt('instant_status'), $task->getInstantStatus());
        }
        
        if ($task && $task->getProtectedFeedbackHtml()) {
            $DIC->logger()->root()->error('ExAutoScore: Adding feedback modal');
            
            try {
                $item_id = "exautoscore_feedback_modal_" . $task->getId();
                
                $modal = ilModalGUI::getInstance();
                $modal->setId($item_id);
                $modal->setType(ilModalGUI::TYPE_LARGE);
                $modal->setBody(ilUtil::stripScriptHTML($task->getProtectedFeedbackHtml(), $this->plugin->getAllowedTags()));
                $modal->setHeading($this->plugin->txt('protected_feedback_html'));
                
                $modal_html = $modal->getHTML();
                
                $button_html = sprintf(
                    '<button type="button" class="btn btn-default" onclick="$(\'#%s\').modal(\'show\'); return false;">%s</button>',
                    $item_id,
                    $this->plugin->txt('show_extended_feedback')
                );
                
                $combined_html = $modal_html . $button_html;
                
                $builder->addProperty(
                    $builder::SEC_TUTOR_EVAL, 
                    '',
                    $combined_html
                );
                
                $DIC->logger()->root()->error('ExAutoScore: Feedback button added successfully');
                
            } catch (Exception $e) {
                $DIC->logger()->root()->error('ExAutoScore: Error adding feedback: ' . $e->getMessage());
            }
        }
        
        $DIC->logger()->root()->error('ExAutoScore buildSubmissionPropertiesAndActions FINISHED');
    }
}