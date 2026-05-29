<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once (__DIR__ . '/class.ilExAutoScorePlugin.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreAssignment.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreTask.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreProvidedFile.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreRequiredFile.php');

/**
 * Connector for the AuDoscore server
 */
class ilExAutoScoreConnector
{
    const NOTIFY_SEND_FAILURE = 'send_failure';
    const NOTIFY_RESULT_FAILURE = 'result_failure';

    /** @var ilExAutoScorePlugin */
    protected ilExAutoScorePlugin $plugin;

    /** @var ilExAutoScoreConfig */
    protected mixed $config;

    /** @var string|null */
    protected ?string $result_uuid = null;

    /** @var string|null */
    protected ?string $result_message = null;

    protected ?string $debug_logs = null;    

    public function __construct()
    {
        $this->plugin = ilExAutoScorePlugin::getInstance();
        $this->config = $this->plugin->getConfig();
    }

    /**
     * Get the ILIAS root logger for [ExAutoScore]-prefixed observability logs.
     *
     * Logs go to the standard ILIAS log target so they end up in data/<client>/log
     * (or wherever ILIAS is configured to write). Use the [ExAutoScore] prefix in
     * messages so they can be grepped/filtered. Pass structured data via the
     * Monolog context array (second argument) instead of stuffing it into the
     * message — that keeps logs machine-parseable.
     */
    protected function logger(): \ilLogger
    {
        global $DIC;
        return $DIC->logger()->root();
    }

    /**
     * @param ilExAssignment $assignment
     */
    public function sendAssignment($assignment)
    {
        $scoreAss = ilExAutoScoreAssignment::findOrGetInstance($assignment->getId());
        $scoreTask = ilExAutoScoreTask::getExampleTask($assignment->getId());

        $scoreTask->setDebugLogs(null);

        $url = $this->config->get('service_assignment_url');
        $timeout = (int) $this->config->get('service_timeout');

        $post = [];
        $post['api_key'] = $this->config->get('service_api_key');
        $post['name'] = $assignment->getTitle();
        $post['priority'] = false;
        $post['return_type'] = 'U';
        $post['return_address'] = $this->plugin->getResultUrl();
        $post['command'] = $scoreAss->getCommand();
        $post['timeout'] = $timeout;
        
        // Debug-Modus mitschicken
        $debugEnabled = $this->config->get('enable_debug_logs') && $scoreAss->getDebugMode();
        if ($debugEnabled) {
            $post['debug_mode'] = 'true';
        }

        $docker = ilExAutoScoreProvidedFile::getAssignmentDocker($assignment->getId());
        if (!empty($docker->getAbsolutePath())) {
            $post['dockerfile'] = new CURLFile($docker->getAbsolutePath(), '', $docker->getFilename());
        }

        $provided = ilExAutoScoreProvidedFile::getAssignmentSupportFiles($assignment->getId());
        $this->addAssignmentFiles($post, $provided, 'files', 'provided.tgz');

        $required = array_merge(
            ilExAutoScoreProvidedFile::getAssignmentSubmitFiles($assignment->getId()),
            ilExAutoScoreRequiredFile::getForAssignment($assignment->getId()));
        $this->addAssignmentFiles($post, $required, 'example', 'required.tgz');

        $submitTime = new ilDateTime(time(), IL_CAL_UNIX);

        $success = $this->callService($url, $post, $timeout);

        $scoreTask->clearSubmissionData();
        $scoreTask->setSubmitTime($submitTime->get(IL_CAL_DATETIME));
        $scoreTask->setUuid($this->getResultUuid());
        $scoreTask->setSubmitSuccess($success);
        $scoreTask->setSubmitMessage($this->getResultMessage());
        
        // Debug-Logs speichern
        if ($debugEnabled && $this->debug_logs) {
            $scoreTask->setDebugLogs($this->debug_logs);
        }
        
        $scoreTask->save();

        if ($success) {
            $scoreAss->setUuid($this->getResultUuid());
            $scoreAss->save();
        }

        return $success;
    }

    /**
     * @param ilExAssignment $assignment
     * @param ilObjUser $user
     * @return bool
     */
    public function sendExampleTask($assignment, $user)
    {
        $scoreAss = ilExAutoScoreAssignment::findOrGetInstance($assignment->getId());
        $scoreTask = ilExAutoScoreTask::getExampleTask($assignment->getId());

        if (empty($scoreAss->getUuid())) {
            return false;
        }        

        $url = $this->config->get('service_task_url');
        $timeout = (int) $this->config->get('service_timeout');

        $post = [];
        $post['assignment'] = $scoreAss->getUuid();
        $post['user_identifier'] = $user->getLogin();
        
        // Debug-Modus mitschicken
        $debugEnabled = $this->config->get('enable_debug_logs') && $scoreAss->getDebugMode();
        $post['debug_mode'] = $debugEnabled ? 'true' : 'false';

        $required = array_merge(
            ilExAutoScoreProvidedFile::getAssignmentSubmitFiles($assignment->getId()),
            ilExAutoScoreRequiredFile::getForAssignment($assignment->getId()));
        $this->addAssignmentFiles($post, $required, 'user_file', 'required.tgz');

        $submitTime = new ilDateTime(time(), IL_CAL_UNIX);

        $success = $this->callService($url, $post, $timeout);

        $scoreTask->clearSubmissionData();
        $scoreTask->setSubmitTime($submitTime->get(IL_CAL_DATETIME));
        $scoreTask->setUuid($this->getResultUuid());
        $scoreTask->setSubmitSuccess($success);
        $scoreTask->setSubmitMessage($this->getResultMessage());
        
        // Debug-Logs speichern
        if ($debugEnabled && $this->debug_logs) {
            $scoreTask->setDebugLogs($this->debug_logs);
        }
        
        $scoreTask->save();

        return $success;
    }

    /**
     * @param ilExSubmission $submission
     * @param ilObjUser $user
     * @return bool
     */
    public function sendSubmission($submission, $user)
    {
        $assignment = $submission->getAssignment();
        $scoreAss = ilExAutoScoreAssignment::findOrGetInstance($assignment->getId());
        $scoreTask = ilExAutoScoreTask::getSubmissionTask($submission);

        $url = $this->config->get('service_task_url');
        $timeout = (int) $this->config->get('service_timeout');

        $post = [];
        $post['assignment'] = $scoreAss->getUuid();
        $post['user_identifier'] = $user->getLogin();

        // Debug-Modus mitschicken
        $debugEnabled = $this->config->get('enable_debug_logs') && $scoreAss->getDebugMode();
        $post['debug_mode'] = $debugEnabled ? 'true' : 'false';

        $this->addSubmissionFiles($post, $submission, 'user_file', 'required.tgz');

        $submitTime = new ilDateTime(time(), IL_CAL_UNIX);

        $success = $this->callService($url, $post, $timeout);

        $scoreTask->clearSubmissionData();
        $scoreTask->setSubmitTime($submitTime->get(IL_CAL_DATETIME));
        $scoreTask->setUuid($this->getResultUuid());
        $scoreTask->setSubmitSuccess($success);
        $scoreTask->setSubmitMessage($this->getResultMessage());

        // Debug-Logs auch hier speichern!
        if ($debugEnabled && $this->debug_logs) {
            $scoreTask->setDebugLogs($this->debug_logs);
        }

        $scoreTask->save();

        // Single line per submission. Only logs the outcome — start logs would
        // double the volume without adding diagnostic value (the outcome line
        // already carries assignment_id + user/team).
        if ($success) {
            $this->logger()->info('[ExAutoScore] send ok', [
                'ass'  => $assignment->getId(),
                'usr'  => $user->getId(),
                'team' => $scoreTask->getTeamId(),
                'uuid' => $scoreTask->getUuid(),
            ]);
        } else {
            // submit_message in DB has the raw error; this central log makes it
            // correlatable with the receiveResult() side and survives task overwrites.
            $this->logger()->warning('[ExAutoScore] send failed', [
                'ass'  => $assignment->getId(),
                'usr'  => $user->getId(),
                'team' => $scoreTask->getTeamId(),
                'msg'  => $this->getResultMessage(),
            ]);
        }

        // HINWEIS: updateMemberStatus() wird NICHT hier aufgerufen, da wir noch keine
        // Korrektur-Ergebnisse haben. Die Bewertung wird erst in receiveResult() geschrieben,
        // wenn die Deadline erreicht ist.

        if (!$success) {
            $this->notifyFailure($assignment, $scoreTask, self::NOTIFY_SEND_FAILURE);
        }
        return $success;
    }

    /**
     * Receive a result from the scoring service
     */
    public function receiveResult()
    {
        global $DIC;

        $files = \GuzzleHttp\Psr7\ServerRequest::normalizeFiles($DIC->http()->request()->getUploadedFiles());
        $result = null;

        $upload_filenames = [];
        foreach ($files as $f) {
            $upload_filenames[] = $f->getClientFilename();
        }

        // ZUERST: Prüfe ob result.json als File hochgeladen wurde (Standard für Multipart)
        foreach ($files as $file) {
            $filename = $file->getClientFilename();

            if ($filename === 'result.json') {
                $filePath = $file->getStream()->getMetadata('uri');
                $fileContent = file_get_contents($filePath);
                $result = json_decode($fileContent, true);
                break;
            }
        }

        // FALLBACK: Versuche Body als JSON (für Nicht-Multipart Requests)
        $body_size = null;
        $body_preview = null;
        if (empty($result)) {
            $content = $DIC->http()->request()->getBody()->getContents();
            $body_size = strlen($content);
            $body_preview = $body_size > 0 ? substr($content, 0, 500) : null;

            if (!empty($content)) {
                $result = json_decode($content, true);
            }
        }

        if (empty($result)) {
            // Either no JSON file uploaded AND no body, or JSON failed to decode.
            // Without this log, the result is silently dropped and impossible to trace.
            $this->logger()->warning('[ExAutoScore] receiveResult got empty/undecodable payload', [
                'uploaded_files' => $upload_filenames,
                'body_size'      => $body_size,
                'body_preview'   => $body_preview,
                'json_error'     => json_last_error_msg(),
            ]);
            return;
        }

        // UUID extrahieren
        if (isset($result['assignment_uuid'])) {
            $this->result_uuid = (string) $result['assignment_uuid'];
        }
        if (isset($result['task_uuid'])) {
            $this->result_uuid = (string) $result['task_uuid'];
        }

        $task = ilExAutoScoreTask::getByUuid($this->result_uuid);

// Fallback: Build-Fehler ohne task_uuid -> schreibe Debug in alle Tasks des Assignments
if (!isset($task) && (($result['phase'] ?? null) === 'build') && !empty($result['assignment_uuid'])) {
    $db = $DIC->database();

    // 1) Assignment-ID via assignment_uuid ermitteln
    $set = $db->queryF(
        "SELECT id FROM exautoscore_assignment WHERE uuid = %s",
        ['text'],
        [(string)$result['assignment_uuid']]
    );
    $row = $db->fetchAssoc($set);

    if ($row && !empty($row['id'])) {
        $ass_id = (int)$row['id'];

        // 2) Alle Tasks des Assignments holen
        $set2 = $db->queryF(
            "SELECT id FROM exautoscore_task WHERE assignment_id = %s",
            ['integer'],
            [$ass_id]
        );

        // 3) Debug/Status in JEDEM Task ablegen (damit GUI/Modal es anzeigt)
        $now = (new ilDateTime(time(), IL_CAL_UNIX))->get(IL_CAL_DATETIME);
        $touched = 0;
        while ($trow = $db->fetchAssoc($set2)) {
            $t = new ilExAutoScoreTask((int)$trow['id']); // dein vorhandener ActiveRecord-Konstruktor

            if (isset($result['debug_logs'])) {
                $t->setDebugLogs($result['debug_logs']);
            }
            if (!empty($result['protected_feedback_html'])) {
                $t->setProtectedFeedbackHtml($result['protected_feedback_html']);
            }
            if (isset($result['protected_status'])) {
                $t->setProtectedStatus($result['protected_status']);
            }

            $t->setInstantStatus($result['instant_status'] ?? 'failed');
            $t->setInstantMessage($result['instant_message'] ?? 'Build-Fehler');
            $t->setReturnTime($now);
            $t->save();
            $touched++;
        }

        $this->logger()->info('[ExAutoScore] receive build-fallback', [
            'ass'            => $ass_id,
            'assignment_uuid' => (string) $result['assignment_uuid'],
            'tasks_updated'  => $touched,
        ]);

        // Wir haben die Debug-Infos verteilt -> OK antworten und den regulären Pfad verlassen
        http_response_code(200);
        echo 'OK';
        return;
    }
}


        if (!isset($task)) {
            // KEY for Issue 1 diagnosis: a result POST arrived but no task matches its uuid.
            // Used to be a silent return — every lost callback was invisible.
            $this->logger()->warning('[ExAutoScore] receive task-not-found', [
                'task_uuid'       => $this->result_uuid,
                'assignment_uuid' => $result['assignment_uuid'] ?? null,
                'phase'           => $result['phase'] ?? null,
                'has_points'      => isset($result['points']),
            ]);
            return;
        }

        $returnTime = new ilDateTime(time(), IL_CAL_UNIX);
        $task->setReturnTime($returnTime->get(IL_CAL_DATETIME));

        $task->setReturncode(isset($result['task_returncode']) ? (int) $result['task_returncode'] : null);
        $task->setReturnPoints(isset($result['points']) ? (float) $result['points'] : null);
        $task->setTaskDuration(isset($result['task_time']) ? (float) $result['task_time'] : null);
        $task->setInstantStatus(isset($result['instant_status']) ? $result['instant_status'] : null);
        $task->setInstantMessage(isset($result['instant_message']) ? $result['instant_message'] : null);
        $task->setProtectedStatus(isset($result['protected_status']) ? $result['protected_status'] : null);
        $task->setProtectedFeedbackText(isset($result['protected_feedback_text']) ? $result['protected_feedback_text'] : null);
        $task->setProtectedFeedbackHtml(isset($result['protected_feedback_html']) ? $result['protected_feedback_html'] : null);

        // Debug-Logs speichern wenn vorhanden
        if (isset($result['debug_logs'])) {
            $task->setDebugLogs($result['debug_logs']);
        }

        $task->save();

        // If the deadline has already passed when the result arrives, publish
        // it directly — through the SAME shared rule as the GUI triggers, so a
        // tutor grade entered before this (delayed) callback is never
        // overwritten and no-score/build-error results are not auto-failed.
        try {
            $assignment = new ilExAssignment($task->getAssignmentId());
            $task->publishToMemberStatusIfDue($assignment);
        } catch (Throwable $e) {
            $DIC->logger()->root()->error('ExAutoScore: auto-publish in receiveResult failed: ' . $e->getMessage());
        }

        $this->saveFeedbackFiles($task, $files);

        $success_failed = false;
        if (isset($result['success'])) {
            if (is_bool($result['success'])) {
                $success_failed = !$result['success'];
            } elseif (is_string($result['success'])) {
                $success_failed = (strtolower($result['success']) == 'false');
            }
        }

        if (empty($result['success']) || $success_failed) {
            $assignment = new ilExAssignment($task->getAssignmentId());
            $this->notifyFailure($assignment, $task, self::NOTIFY_RESULT_FAILURE);
        }

        // Single line per successful callback. Tells us at a glance whether the
        // result actually carried verifiable content (points + feedback sizes)
        // — useful when "Studi sieht kein Feedback" needs correlation to a callback.
        $this->logger()->info('[ExAutoScore] receive ok', [
            'ass'        => $task->getAssignmentId(),
            'task'       => $task->getId(),
            'usr'        => $task->getUserId(),
            'team'       => $task->getTeamId(),
            'points'     => $task->getReturnPoints(),
            'rc'         => $task->getReturnCode(),
            'html_size'  => strlen((string) $task->getProtectedFeedbackHtml()),
            'text_size'  => strlen((string) $task->getProtectedFeedbackText()),
            'published'  => ($all_deadlines_reached ?? false) && !empty($affected_users ?? null),
        ]);
    }

    /**
     * Save the feedback files
     * @param ilExAutoScoreTask $task
     * @param \GuzzleHttp\Psr7\UploadedFile[] $files
     */
    protected function saveFeedbackFiles($task, $files)
    {
        global $DIC;

        $assignment = new ilExAssignment($task->getAssignmentId());

        // Betroffene User ermitteln (funktioniert für Teams und Einzeluser)
        $affected_users = $task->getAffectedUserIds();
        if (empty($affected_users)) {
            return;
        }

        // Prüfe ob Deadline erreicht ist - Feedback-Dateien nur NACH Deadline speichern
        $all_deadlines_reached = true;
        $now = time();

        foreach ($affected_users as $uid) {
            $personal_deadline = (int) $assignment->getPersonalDeadline($uid);
            $general_deadline = (int) ($assignment->getDeadline() ?? 0);
            $effective_deadline = $personal_deadline > 0 ? $personal_deadline : $general_deadline;

            if ($effective_deadline > 0) {
                if ($now < $effective_deadline) {
                    $all_deadlines_reached = false;
                    break;
                }
            }
        }

        // Wenn Deadline nicht erreicht -> Feedback-Dateien NICHT speichern
        if (!$all_deadlines_reached) {
            return;
        }

        // Für Feedback-Dateien: nimm einen beliebigen User (bei Teams ist es egal welcher)
        $user_id = $affected_users[0];

        // Team-Objekt nur bei Team-Aufgaben
        $team = null;
        if (!empty($task->getTeamId())) {
            $team = new ilExAssignmentTeam($task->getTeamId());
        }

        $submission = new ilExSubmission($assignment, $user_id, $team);
        $feedback_id = $submission->getFeedbackId();

        $fstorage = new ilFSStorageExercise($assignment->getExerciseId(), $assignment->getId());
        $fstorage->create();
        $fb_path = $fstorage->getFeedbackPath($feedback_id);

        // delete old feedback files and create the directory new
        $fstorage->deleteDirectory($fb_path);
        $fb_path = $fstorage->getFeedbackPath($feedback_id);

        // HINWEIS: feedback.html wird NICHT mehr gespeichert, da das HTML über
        // den "Erweitertes Feedback" Modal-Button angezeigt wird (nach Deadline).

        foreach ($files as $file) {
            // Überspringe result.json - das wurde bereits verarbeitet
            if ($file->getClientFilename() !== 'result.json') {
                $file->moveTo($fb_path . "/". ilUtil::getASCIIFilename($file->getClientFilename()));
            }
        }
    }

    /**
     * Get the assignment uuid that is returned
     * @return string|null
     */
    public function getResultUuid(): ?string {
        return $this->result_uuid;
    }

    /**
     * @return string|null
     */
    public function getResultMessage(): ?string {
        return $this->result_message;
    }

    /**
     * Call the external service using native PHP cURL
     */
    protected function callService($url, $post, $timeout): bool
    {
        try {
            $curl = curl_init();
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FAILONERROR => false,
                CURLOPT_HEADER => false,
            ]);
            
            $proxy = ilProxySettings::_getInstance();
            if ($proxy->isActive()) {
                curl_setopt($curl, CURLOPT_HTTPPROXYTUNNEL, true);
                if (!empty($proxy->getHost())) {
                    curl_setopt($curl, CURLOPT_PROXY, $proxy->getHost());
                }
                if (!empty($proxy->getPort())) {
                    curl_setopt($curl, CURLOPT_PROXYPORT, $proxy->getPort());
                }
            }
            
            $result = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($curl);
            $curl_errno = curl_errno($curl);

            curl_close($curl);
            
            if ($curl_errno !== 0) {
                $this->result_uuid = null;

                // Spezifische Hinweise für häufige cURL Fehler
                $hint = '';
                switch ($curl_errno) {
                    case 6: // CURLE_COULDNT_RESOLVE_HOST
                        $hint = ' → Mögliche Ursachen: DNS-Problem, falsche URL, keine Internetverbindung';
                        break;
                    case 7: // CURLE_COULDNT_CONNECT
                        $hint = ' → Mögliche Ursachen: Server ist nicht erreichbar, Firewall blockiert Verbindung, Server ist offline';
                        break;
                    case 28: // CURLE_OPERATION_TIMEDOUT
                        $hint = ' → Mögliche Ursachen: Server antwortet nicht rechtzeitig, Netzwerk zu langsam, Timeout zu kurz konfiguriert';
                        break;
                    case 35: // CURLE_SSL_CONNECT_ERROR
                        $hint = ' → Mögliche Ursachen: SSL/TLS Fehler, Zertifikatsproblem';
                        break;
                    case 56: // CURLE_RECV_ERROR
                        $hint = ' → Mögliche Ursachen: Verbindung wurde während der Übertragung unterbrochen';
                        break;
                }

                $this->result_message = 'cURL Error #' . $curl_errno . ': ' . $curl_error . $hint;
                return false;
            }
            
            if ($result === false) {
                $this->result_uuid = null;
                $this->result_message = 'cURL execution failed';
                return false;
            }
            
            if ($http_code >= 400) {
                $this->result_uuid = null;
                $this->result_message = 'HTTP Error ' . $http_code . ': ' . substr($result, 0, 1000);
                return false;
            }
            
            if (empty($result)) {
                $this->result_uuid = null;
                $this->result_message = 'Empty response from service (HTTP ' . $http_code . ')';
                return false;
            }
            
            $decoded_result = json_decode($result, true);
            $json_error = json_last_error();
            
            if ($json_error !== JSON_ERROR_NONE) {
                $this->result_uuid = null;
                $this->result_message = 'Invalid JSON response: ' . substr($result, 0, 500);
                return false;
            }
            
            if (isset($decoded_result['error'])) {
                $this->result_uuid = null;
                $this->result_message = 'Service error: ' . $decoded_result['error'];
                
                if (isset($decoded_result['details'])) {
                    $this->result_message .= ' | Details: ' . $decoded_result['details'];
                }
                if (isset($decoded_result['traceback'])) {
                    $this->result_message .= ' | Traceback: ' . substr($decoded_result['traceback'], 0, 500);
                }
                
                return false;
            }
            
            // Process the result
            if (isset($decoded_result['assignment_uuid'])) {
                $this->result_uuid = (string) $decoded_result['assignment_uuid'];
            }
            if (isset($decoded_result['task_uuid'])) {
                $this->result_uuid = (string) $decoded_result['task_uuid'];
            }
            
            if (isset($decoded_result['message'])) {
                $this->result_message = (string) $decoded_result['message'];
            } elseif (isset($decoded_result['msg'])) {
                $this->result_message = (string) $decoded_result['msg'];
            } else {
                $this->result_message = 'Success';
            }
            
            // Debug-Logs extrahieren
            if (isset($decoded_result['debug_logs'])) {
                $this->debug_logs = $decoded_result['debug_logs'];
            }
            
            $success = isset($decoded_result['success']) ? (bool) $decoded_result['success'] : false;
            
            return $success;
            
        }
        catch(Exception $e) {
            if (isset($curl) && is_resource($curl)) {
                curl_close($curl);
            }
            
            $this->result_uuid = null;
            $this->result_message = 'Connection exception: ' . $e->getMessage();
            
            return false;
        }
    }

    /**
     * Add the files of an assignment to the post request
     *
     * @param array $post
     * @param ilExAutoScoreFileBase[] $files
     * @param string $postvar
     * @param string $tarname
     */
    protected function addAssignmentFiles(&$post, $files, $postvar, $tarname)
    {
        if (count($files) == 1) {
            $file = array_pop($files);
            if (!empty($file->getAbsolutePath())) {
                $post[$postvar] = new CURLFile($file->getAbsolutePath(), '', $file->getFilename());
            }
        }
        elseif (count($files) > 1) {
            $postfiles = [];
            foreach ($files as $file) {
                $postfiles[$file->getFilename()] = $file->getAbsolutePath();
            }
            $tar = $this->packFiles($postfiles);
            if (!empty($tar)) {
                $post[$postvar] = new CURLFile($tar, '', $tarname);
            }
        }
    }

    /**
     * Add the files of a submission to the post request
     * @param array $post
     * @param ilExSubmission $submission
     * @param string $postvar
     * @param string $tarname
     */
    protected function addSubmissionFiles(&$post, $submission, $postvar, $tarname)
    {
        // add the submit files provided by the assignment
        $postfiles = [];
        foreach(ilExAutoScoreProvidedFile::getAssignmentSubmitFiles($submission->getAssignment()->getId()) as $file) {
            $postfiles[$file->getFilename()] = $file->getAbsolutePath();
        }

        // add the required files which are submitted by the user
        $submitted = [];
        foreach ($submission->getFiles() as $file) {
            // $file['filetitle'] is basename
            // $file['filename'] is absolute path
            $submitted[$file["filetitle"]] = $file['filename'];
        }
        foreach (ilExAutoScoreRequiredFile::getForAssignment($submission->getAssignment()->getId()) as $file) {
            if (isset($submitted[$file->getFilename()])) {
                $postfiles[$file->getFilename()] = $submitted[$file->getFilename()];
            }
        }

        // put the single file or a tar of all files to the post
        if (count($postfiles) == 1) {
            foreach ($postfiles as $name => $path) {
                $post[$postvar] = new CURLFile($path, '', $name);
                break;
            }
        }
        elseif (count($postfiles) > 1) {
            $tar = $this->packFiles($postfiles);
            if (!empty($tar)) {
                $post[$postvar] = new CURLFile($tar, '', $tarname);
            }
         }
    }

    /**
     * Pack files for transmission
     * @param array $files  name => absolute path
     * @return string absolute path
     */
    protected function packFiles($files)
    {
        $tarcmd = $this->plugin->getConfig()->get('tar_command');
        if (empty($tarcmd)) {
            return '';
        }

        $temproot = ILIAS_DATA_DIR . '/' . CLIENT_ID . '/temp';
        $tempdir = uniqid('exautoscore', true);
        $temptar = uniqid('', true) . '.tgz';

        mkdir($temproot . '/' . $tempdir);
        foreach ($files as $name => $path) {
            copy($path, $temproot . '/' . $tempdir . '/' . $name);
        }

        $curdir = getcwd();
        chdir($temproot . '/' . $tempdir);
        exec($tarcmd . ' ' . $temptar . ' *');
        chdir($curdir);

        return $temproot . '/' . $tempdir . '/' . $temptar;
    }

    /**
     * Send a failure notification
     * @param ilExAssignment    $assignment
     * @param ilExAutoScoreTask $scoreTask
     * @param string            $type
     */
    protected function notifyFailure($assignment, $scoreTask, $type)
    {
        global $DIC;
        $lng = $DIC->language();
        $lng->loadLanguageModule('exc');

        $scoreAss = ilExAutoScoreAssignment::findOrGetInstance($scoreTask->getAssignmentId());
        if (empty($scoreAss->getFailureMails())) {
            return;
        }

        switch ($type) {
            case self::NOTIFY_SEND_FAILURE:
                $subject = sprintf($this->plugin->txt('failure_subject_send'), $assignment->getTitle());
                break;
            case self::NOTIFY_RESULT_FAILURE:
                $subject = sprintf($this->plugin->txt('failure_subject_result'), $assignment->getTitle());
                break;
        }

        $info = [];

        $info[$lng->txt('exc')] = ilObject::_lookupTitle($assignment->getExerciseId());
        $info[$lng->txt('exc_assignment')] = $assignment->getTitle();

        // Betroffene User ermitteln (funktioniert für Teams und Einzeluser)
        $affected_users = $scoreTask->getAffectedUserIds();
        if (!empty($affected_users)) {
            $names = [];
            foreach ($affected_users as $user_id) {
                $names[] = ilObjUser::_lookupFullname($user_id);
            }

            if (!empty($scoreTask->getTeamId())) {
                $info[$lng->txt('exc_team')] = '(' . $scoreTask->getTeamId() . ') ' . implode(', ', $names);
            } else {
                $info[$lng->txt('user')] = $names[0];
            }
        }

        if (!empty($scoreTask->getSubmitTime())) {
            $info[$this->plugin->txt('submit_time')] = ilDatePresentation::formatDate(new ilDateTime($scoreTask->getSubmitTime(), IL_CAL_DATETIME));
        }
        if (!empty($scoreTask->getSubmitMessage())) {
            $info[$this->plugin->txt('submit_message')] = $scoreTask->getSubmitMessage();
        }
        if (!empty($scoreTask->getReturnTime())) {
            $info[$this->plugin->txt('return_time')] = ilDatePresentation::formatDate(new ilDateTime($scoreTask->getReturnTime(), IL_CAL_DATETIME));
        }
        if (!empty($scoreTask->getReturnCode())) {
            $info[$this->plugin->txt('return_code')] = $scoreTask->getReturnCode();
        }
        if (!empty($scoreTask->getTaskDuration())) {
            $info[$this->plugin->txt('task_duration')] = $scoreTask->getTaskDuration();
        }
        if (!empty($scoreTask->getInstantMessage())) {
            $info[$this->plugin->txt('instant_message')] = $scoreTask->getInstantMessage();
        }
        if (!empty($scoreTask->getInstantStatus())) {
            $info[$this->plugin->txt('instant_status')] = $scoreTask->getInstantStatus();
        }

        $body = '';

        foreach ($info as $label => $content) {
            $body .= "$label: $content\n";
        }
    }

    /**
     * Get debug logs returned from service
     * @return string|null
     */
    public function getDebugLogs(): ?string
    {
        return $this->debug_logs;
    }

}