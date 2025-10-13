<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once (__DIR__ . '/models/class.ilExAutoScoreAssignment.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreTask.php');
require_once (__DIR__ . '/traits/trait.ilExAutoScoreGUIBase.php');
require_once (__DIR__ . '/class.ilExAutoScoreConnector.php');

/**
 * Specific settings of an auto score assignment
 * @ilCtrl_IsCalledBy ilExAutoScoreSettingsGUI: ilExAssTypeAutoScoreUserGUI, ilExAssTypeAutoScoreTeamGUI, ilExAssignmentEditorGUI
 */
class ilExAutoScoreSettingsGUI
{
    use ilExAutoScoreGUIBase;

    /** @var ilExAutoScorePlugin */
    protected mixed $plugin;

    /** @var ilExAssignment */
    protected mixed $assignment;

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
    public function executeCommand(): void
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

        if (ilExAutoScoreTask::hasTasks($this->assignment->getId())) {
            $this->tpl->setOnScreenMessage('info', $this->plugin->txt('info_existing_tasks'));
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
            $assCont = ilExAutoScoreProvidedFile::getAssignmentDocker($this->assignment->getId());

            $request = $DIC->http()->request();
            $params = $request->getParsedBody();

            $resetNeeded = false;
            $updateNeeded = false;

            $assCont->setDescription((string) $params['exautoscore_docker_description']);
            $assCont->setPurpose(ilExAutoScoreProvidedFile::PURPOSE_DOCKER);
            $assCont->setPublic(false);
            $assCont->save();

            if ($assCont->storeUploadedFile()) {
                $resetNeeded = true;
            }

            if ($assAuto->getCommand() != (string) $params['exautoscore_docker_command']) {
                $resetNeeded = true;
            }

            if ($assAuto->getMinPoints() != (float) $params['exautoscore_min_points']) {
                $updateNeeded = true;
            }

            $assAuto->setCommand((string) $params['exautoscore_docker_command']);
            $assAuto->setMinPoints((float) $params['exautoscore_min_points']);
            $assAuto->setFailureMails((string) $params['exautoscore_failure_mails']);
            
            // NEU: Debug-Modus speichern (nur für Admins)
            if ($this->plugin->hasAdminAccess()) {
                $assAuto->setDebugMode(isset($params['exautoscore_debug_mode']) && (bool) $params['exautoscore_debug_mode']);
            }
            
            $assAuto->save();

            $message = $this->plugin->txt('correction_settings_saved');
            if ($resetNeeded) {
                ilExAutoScoreAssignment::resetCorrection($this->assignment->getId());
                if (ilExAutoScoreTask::hasTasks($this->assignment->getId())) {
                    $message .= ' ' . $this->plugin->txt('please_send_assignment_and_tasks');
                }
                else {
                    $message .= ' ' . $this->plugin->txt('please_send_assignment');
                }
            }
            elseif ($updateNeeded) {
                ilExAutoScoreTask::updateAllSubmissions($this->assignment->getId());
                $message = $this->plugin->txt('correction_settings_saved_with_update');
            }
            $this->tpl->setOnScreenMessage('success', $message, true);

            $this->ctrl->redirect($this, 'showSettings');
        }
        else {
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
        }
    }


    /**
     * @return ilPropertyFormGUI
     */    public function initSettingsForm(): ilPropertyFormGUI
    {
        $assAuto = ilExAutoScoreAssignment::findOrGetInstance($this->assignment->getId());
        $assCont = ilExAutoScoreProvidedFile::getAssignmentDocker($this->assignment->getId());
        $assTask = ilExAutoScoreTask::getExampleTask($this->assignment->getId());

        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->plugin->txt('autoscore_settings'));
        $form->addCommandButton('saveSettings', $this->lng->txt('save_settings'));

        $contUploadFile = new ilFileInputGUI($this->plugin->txt('purpose_docker'), 'exautoscore_docker_upload');
        $info = $this->plugin->txt('purpose_docker_info');
        if (!empty($assCont->getHash())) {
            $this->ctrl->setParameter($this->parentGUI, 'file_id', $assCont->getId());
            $link = $this->ctrl->getLinkTarget($this->parentGUI, 'downloadProvidedFile');
            $info .= '<p><strong>' . $this->plugin->txt('existing_file') . ':</strong> '
                    .'<a href="' . $link . '">' . $assCont->getFilename() . '</a></p>';
        }
        else {
            $contUploadFile->setRequired(true);
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
        $minPoints->setDecimals(2);
        $minPoints->setSize(10);
        $minPoints->setValue(empty($assAuto->getMinPoints()) ? null : $this->formatFloatForInput($assAuto->getMinPoints()));
        $form->addItem($minPoints);

        $failureMails = new ilTextInputGUI($this->plugin->txt('failure_mails'), 'exautoscore_failure_mails');
        $failureMails->setInfo($this->plugin->txt('failure_mails_info'));
        $failureMails->setValue($assAuto->getFailureMails());
        $form->addItem($failureMails);

        // NEU: Debug-Modus (nur für Admins sichtbar/editierbar)
        if ($this->plugin->hasAdminAccess() && $this->plugin->getConfig()->get('enable_debug_logs')) {
            $debugMode = new ilCheckboxInputGUI(
                $this->plugin->txt('debug_mode'), 
                'exautoscore_debug_mode'
            );
            $debugMode->setInfo($this->plugin->txt('debug_mode_info'));
            $debugMode->setChecked($assAuto->getDebugMode());
            $form->addItem($debugMode);
        }

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

            // VEREINFACHT: Status mit gleichem Styling wie User-Ansicht
            $status = $assTask->getInstantStatus() ?: $assTask->getProtectedStatus();
            if (!empty($status)) {
                $statusField = new ilNonEditableValueGUI($this->plugin->txt('instant_status'), 'exautoscore_status', true);
                // Verwende die gleiche getStyledStatusSymbol() Methode wie in der User-Ansicht
                $statusField->setValue($this->parentGUI->getStyledStatusSymbol($status));
                $form->addItem($statusField);
            }

            // VEREINFACHT: Message mit gleichem Styling wie User-Ansicht
            $message = $assTask->getInstantMessage() ?: $assTask->getProtectedFeedbackText();
            if (!empty($message)) {
                $messageField = new ilNonEditableValueGUI($this->plugin->txt('instant_message'), 'exautoscore_message', true);
                // Verwende die gleiche formatInstantMessage() Methode wie in der User-Ansicht
                $messageField->setValue($this->parentGUI->formatInstantMessage($message));
                $form->addItem($messageField);
            }

            if (!empty($assTask->getProtectedFeedbackHtml())) {
                $protectedFeedbackHtml = new ilNonEditableValueGUI($this->plugin->txt('protected_feedback_html'), 'exautoscore_protected_feedback_html', true);
                $item_id = "exautoscore_feedback_html_" . $this->assignment->getId();
                
                $modal = ilModalGUI::getInstance();
                $modal->setId($item_id);
                $modal->setType(ilModalGUI::TYPE_LARGE);
                
                $feedbackHtml = $assTask->getProtectedFeedbackHtml();
                $cleanFeedbackHtml = preg_replace('/<details[^>]*>.*?<\/details>/is', '', $feedbackHtml);                                                 
                
                $modal->setBody(ilUtil::stripScriptHTML($cleanFeedbackHtml, $this->plugin->getAllowedTags()));
                $modal->setHeading($this->plugin->txt('protected_feedback_html'));
                
                $button_html = sprintf(
                    '<button type="button" class="btn btn-default" onclick="$(\'#%s\').modal(\'show\');">%s</button>',
                    $item_id,
                    $this->plugin->txt('show_extended_feedback')
                );
                                
                $protectedFeedbackHtml->setValue($modal->getHTML() . $button_html);
                $form->addItem($protectedFeedbackHtml);
            }

            // NEU: Debug-Logs anzeigen (nur wenn aktiviert und vorhanden)
            if (!empty($assTask->getDebugLogs()) 
                && $this->plugin->getConfig()->get('enable_debug_logs')
                && $assAuto->getDebugMode()) {
                
                $debugLogs = new ilNonEditableValueGUI(
                    $this->plugin->txt('debug_logs'), 
                    'exautoscore_debug_logs', 
                    true
                );

                // Zeige das Datum der Log-Erstellung
                $logDate = '';
                if (!empty($assTask->getReturnTime())) {
                    $time = new ilDateTime($assTask->getReturnTime(), IL_CAL_DATETIME);
                    $logDate = ' (' . ilDatePresentation::formatDate($time) . ')';
                }                
                
                $item_id = "exautoscore_debug_logs_modal_" . $this->assignment->getId();
                
                $modal = ilModalGUI::getInstance();
                $modal->setId($item_id);
                $modal->setType(ilModalGUI::TYPE_LARGE);
                $formattedLogs = $this->formatDebugLogs($assTask->getDebugLogs());                
                $modal->setBody(
                    '<pre style="max-height:70vh;overflow:auto;background:white;color:black;padding:15px;border-radius:4px;font-family:monospace;font-size:12px;line-height:1.4;">' 
                    . htmlspecialchars($formattedLogs) 
                    . '</pre>'
                );
                $modal->setHeading($this->plugin->txt('debug_logs'));
                
                $button_html = sprintf(
                    '<button type="button" class="btn btn-warning" onclick="$(\'#%s\').modal(\'show\');">%s%s</button>',
                    $item_id,
                    $this->plugin->txt('show_debug_logs'),
                    $logDate
                );
                
                $debugLogs->setValue($modal->getHTML() . $button_html);
                $form->addItem($debugLogs);
            }
        }

        return $form;
    }


    public function sendAssignment()
    {
        $connector = new ilExAutoScoreConnector();
        if ($connector->sendAssignment($this->assignment)) {
            $this->tpl->setOnScreenMessage('success', sprintf($this->plugin->txt('assignment_send_success'), $connector->getResultMessage())
                . sprintf('<p class="small"><a href="%s">%s</a></p>', $this->ctrl->getLinkTarget($this, 'showSettings'), $this->plugin->txt('refresh_screen_link'))
                , true);
        }
        else {
            $this->tpl->setOnScreenMessage('failure', sprintf($this->plugin->txt('assignment_send_failure'), $connector->getResultMessage()), true);
        }
        $this->ctrl->redirect($this, 'showSettings');
    }


    public function sendExampleTask()
    {
        global $DIC;
        $connector = new ilExAutoScoreConnector();
        if ($connector->sendExampleTask($this->assignment, $DIC->user())) {
            $this->tpl->setOnScreenMessage('success', sprintf($this->plugin->txt('example_task_send_success'), $connector->getResultMessage())
                . sprintf('<p class="small"><a href="%s">%s</a></p>', $this->ctrl->getLinkTarget($this, 'showSettings'), $this->plugin->txt('refresh_screen_link'))
                , true);
        }
        else {
            $this->tpl->setOnScreenMessage('failure', sprintf($this->plugin->txt('example_task_send_failure'), $connector->getResultMessage()), true);
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

       $this->tpl->setOnScreenMessage('success', $this->plugin->txt('all_tasks_sent'), true);
       $this->ctrl->redirect($this, 'showSettings');
    }


    public function setToolbar(): void
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

    private function formatFloatForInput(?float $value, int $decimals = 2): ?string
    {
        if ($value === null) {
            return null;
        }
        return sprintf('%.' . $decimals . 'f', $value);
    }


    /**
     * Formatiert Debug-Logs für bessere Lesbarkeit und einheitliche Zeitstempel
     * @param string $logs Rohe Debug-Logs
     * @return string Formatierte Debug-Logs
     */
    private function formatDebugLogs(string $logs): string 
    {
        if (empty($logs)) {
            return $logs;
        }
        
        // Zeilen aufteilen
        $lines = explode("\n", $logs);
        $formatted_lines = [];
        
        foreach ($lines as $line) {
            // Celery Timestamps normalisieren: [2025-10-13 06:36:52,313: -> [13.10.2025 08:36:52:
            // Füge +2h für CEST hinzu
            $line = preg_replace_callback(
                '/\[(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2}),\d+:/',
                function($matches) {
                    $hour = (int)$matches[4];
                    $adjusted_hour = ($hour + 2) % 24; // +2h für CEST
                    return sprintf('[%s.%s.%s %02d:%s:%s:', 
                        $matches[3], $matches[2], $matches[1], 
                        $adjusted_hour, $matches[5], $matches[6]);
                },
                $line
            );
            
            // Journalctl Timestamps: Okt 13 08:36:52 -> 13.10.2025 08:36:52
            $line = preg_replace('/Okt (\d+) (\d{2}):(\d{2}):(\d{2})/', 
                            '13.10.2025 $2:$3:$4', $line);
            
            // Weitere Monate falls nötig
            $line = preg_replace('/Nov (\d+) (\d{2}):(\d{2}):(\d{2})/', 
                            '${1}.11.2025 $2:$3:$4', $line);
            $line = preg_replace('/Dez (\d+) (\d{2}):(\d{2}):(\d{2})/', 
                            '${1}.12.2025 $2:$3:$4', $line);
            $line = preg_replace('/Jan (\d+) (\d{2}):(\d{2}):(\d{2})/', 
                            '${1}.01.2026 $2:$3:$4', $line);
            
            $formatted_lines[] = $line;
        }
        
        // Header mit Erklärung hinzufügen
        $header = "=== DEBUG-PROTOKOLL ===\n";
        $header .= "Zeitstempel wurden auf CEST normalisiert.\n";
        $header .= "Quelle: Celery-Logs, Docker-Build-Output, Task-Ausführung\n\n";
        
        return $header . implode("\n", $formatted_lines);
    }    

}
