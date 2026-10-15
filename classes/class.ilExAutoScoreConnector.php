<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once (__DIR__ . '/class.ilExAutoScorePlugin.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreAssignment.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreTask.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreProvidedFile.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreRequiredFile.php');
require_once (__DIR__ . '/class.ilExAutoScoreSubmissionFiles.php');
require_once (__DIR__ . '/class.ilExAutoScoreDeadline.php');

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

    /** @var string[] */
    protected array $temp_files = [];

    /** @var string[] temp directories created by packFiles() */
    protected array $temp_dirs = [];

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
        // Send the previous service UUID so the service rebuilds the SAME assignment instead
        // of creating a new one (and leaving the old image behind). service_uuid first: uuid is empty after a reset.
        $previousUuid = $scoreAss->getServiceUuid() !== '' ? $scoreAss->getServiceUuid() : $scoreAss->getUuid();
        if ($previousUuid !== '') {
            $post['uuid'] = $previousUuid;
        }
        $post['name'] = $assignment->getTitle();
        $post['priority'] = false;
        $post['return_type'] = 'U';
        $post['return_address'] = $this->plugin->getResultUrl();
        $post['command'] = $scoreAss->getCommand();
        $post['timeout'] = $timeout;
        
        // Pass debug mode to the service
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
        $this->removeTempDirs();

        $scoreTask->clearSubmissionData();
        $scoreTask->setSubmitTime($submitTime->get(IL_CAL_DATETIME));
        $scoreTask->setUuid($this->getResultUuid());
        $scoreTask->setSubmitSuccess($success);
        $scoreTask->setSubmitMessage($this->getResultMessage());
        
        // Store debug logs
        if ($debugEnabled && $this->debug_logs) {
            $scoreTask->setDebugLogs($this->debug_logs);
        }
        
        $scoreTask->save();

        if ($success) {
            $scoreAss->setUuid($this->getResultUuid());
            $scoreAss->setServiceUuid((string) $this->getResultUuid());
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
        $post['api_key'] = $this->config->get('service_api_key');
        $post['assignment'] = $scoreAss->getUuid();
        $post['user_identifier'] = $user->getLogin();

        // Pass debug mode to the service
        $debugEnabled = $this->config->get('enable_debug_logs') && $scoreAss->getDebugMode();
        $post['debug_mode'] = $debugEnabled ? 'true' : 'false';

        $required = array_merge(
            ilExAutoScoreProvidedFile::getAssignmentSubmitFiles($assignment->getId()),
            ilExAutoScoreRequiredFile::getForAssignment($assignment->getId()));
        $this->addAssignmentFiles($post, $required, 'user_file', 'required.tgz');

        $submitTime = new ilDateTime(time(), IL_CAL_UNIX);

        $success = $this->callService($url, $post, $timeout);
        $this->removeTempDirs();

        $scoreTask->clearSubmissionData();
        $scoreTask->setSubmitTime($submitTime->get(IL_CAL_DATETIME));
        $scoreTask->setUuid($this->getResultUuid());
        $scoreTask->setSubmitSuccess($success);
        $scoreTask->setSubmitMessage($this->getResultMessage());
        
        // Store debug logs
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
        $post['api_key'] = $this->config->get('service_api_key');
        $post['assignment'] = $scoreAss->getUuid();
        $post['user_identifier'] = $user->getLogin();

        // Pass debug mode to the service
        $debugEnabled = $this->config->get('enable_debug_logs') && $scoreAss->getDebugMode();
        $post['debug_mode'] = $debugEnabled ? 'true' : 'false';

        $this->addSubmissionFiles($post, $submission, 'user_file', 'required.tgz');

        $submitTime = new ilDateTime(time(), IL_CAL_UNIX);

        $success = $this->callService($url, $post, $timeout);
        $this->removeTempDirs();
        foreach ($this->temp_files as $temp_file) {
            @unlink($temp_file);
        }
        $this->temp_files = [];

        $scoreTask->clearSubmissionData();
        $scoreTask->setSubmitTime($submitTime->get(IL_CAL_DATETIME));
        $scoreTask->setUuid($this->getResultUuid());
        $scoreTask->setSubmitSuccess($success);
        $scoreTask->setSubmitMessage($this->getResultMessage());

        // Store debug logs
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

        // No updateMemberStatus() here: there is no result yet; grading happens in receiveResult()
        // once the deadline has passed.

        if (!$success) {
            $this->notifyFailure($assignment, $scoreTask, self::NOTIFY_SEND_FAILURE);
            // Also alert the operators: a send failure is a service problem, not the tutor's.
            // Globally throttled, so one outage triggers ONE mail, not one per exercise.
            $this->notifyServiceOutage($assignment);
        } else {
            // Service accepts again: re-arm the outage alert here, not only in receiveResult(),
            // since results only arrive when someone submits and the next outage would go unnoticed.
            $this->clearServiceOutageFlag();
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

        // First: check for an uploaded result.json (default for multipart)
        foreach ($files as $file) {
            $filename = $file->getClientFilename();

            if ($filename === 'result.json') {
                $filePath = $file->getStream()->getMetadata('uri');
                $fileContent = file_get_contents($filePath);
                $result = json_decode($fileContent, true);
                break;
            }
        }

        // Fallback: parse the body as JSON (non-multipart requests)
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

        // Extract UUID
        if (isset($result['assignment_uuid'])) {
            $this->result_uuid = (string) $result['assignment_uuid'];
        }
        if (isset($result['task_uuid'])) {
            $this->result_uuid = (string) $result['task_uuid'];
        }

        $task = ilExAutoScoreTask::getByUuid($this->result_uuid);

// Fallback: build error without task_uuid -> write debug info to all tasks of the assignment
if (!isset($task) && (($result['phase'] ?? null) === 'build') && !empty($result['assignment_uuid'])) {
    $db = $DIC->database();

    // 1) Look up the assignment ID via assignment_uuid
    $set = $db->queryF(
        "SELECT id FROM exautoscore_assignment WHERE uuid = %s",
        ['text'],
        [(string)$result['assignment_uuid']]
    );
    $row = $db->fetchAssoc($set);

    if ($row && !empty($row['id'])) {
        $ass_id = (int)$row['id'];

        // 2) Fetch all tasks of the assignment
        $set2 = $db->queryF(
            "SELECT id FROM exautoscore_task WHERE assignment_id = %s",
            ['integer'],
            [$ass_id]
        );

        // 3) Store debug/status in EVERY task (so the GUI/modal shows it)
        $now = (new ilDateTime(time(), IL_CAL_UNIX))->get(IL_CAL_DATETIME);
        $touched = 0;
        while ($trow = $db->fetchAssoc($set2)) {
            $t = new ilExAutoScoreTask((int)$trow['id']);

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

        // Debug info distributed -> answer OK and skip the regular path
        http_response_code(200);
        echo 'OK';
        return;
    }
}


        if (!isset($task)) {
            // A result POST arrived but no task matches its uuid; log it so lost callbacks are visible.
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

        // Store debug logs if present
        if (isset($result['debug_logs'])) {
            $task->setDebugLogs($result['debug_logs']);
        }

        $task->save();

        // If the deadline has already passed when the result arrives, publish
        // it directly — through the SAME shared rule as the GUI triggers, so a
        // tutor grade entered before this (delayed) callback is never
        // overwritten and no-score/build-error results are not auto-failed.
        $published = false;
        try {
            $assignment = new ilExAssignment($task->getAssignmentId());
            $published = $task->publishToMemberStatusIfDue($assignment);
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

        $phase = $result['phase'] ?? null;
        $failed = empty($result['success']) || $success_failed;
        $isExample = ($task->getUserId() === null && $task->getTeamId() === null);
        // Sample solution counts as failed if success is missing OR the container ran
        // but instant_status is 'failed' (e.g. the sample solution does not compile).
        $sampleFailed = $failed || strtolower((string) $task->getInstantStatus()) === 'failed';

        if ($phase === 'build') {
            // Image could not be built (setup broken): throttled failure mail.
            // An unreachable service is reported separately via NOTIFY_SEND_FAILURE.
            $assignment = new ilExAssignment($task->getAssignmentId());
            $this->notifyFailure($assignment, $task, self::NOTIFY_RESULT_FAILURE);
        } elseif ($isExample) {
            // Container ran: report the sample solution result (success OR failure) to the tutor.
            // Student submissions intentionally trigger nothing here.
            $assignment = new ilExAssignment($task->getAssignmentId());
            $this->notifySampleResult(
                $assignment,
                $task,
                $sampleFailed ? 'sample_failed_subject' : 'sample_success_subject'
            );
        }

        // Reset the failure-mail throttle once a real run result arrives, regardless of the score.
        // On a build error it stays set (outage ongoing -> only one mail).
        if ($phase !== 'build') {
            $scoreAss = ilExAutoScoreAssignment::findOrGetInstance($task->getAssignmentId());
            if ($scoreAss->getFailureMailSent()) {
                $scoreAss->setFailureMailSent(false);
                $scoreAss->store();
            }
            // Service responded, so it works again: re-arm the operator alert.
            $this->clearServiceOutageFlag();
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
            'published'  => $published,
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

        // Determine affected users (works for teams and single users)
        $affected_users = $task->getAffectedUserIds();
        if (empty($affected_users)) {
            return;
        }

        // feedback files only after the submission phase of all affected users has ended
        if (!ilExAutoScoreDeadline::hasEndedForAll($assignment, $affected_users)) {
            return;
        }

        // Any affected user will do for feedback files (for teams it does not matter which)
        $user_id = (int) $affected_users[0];

        // feedback.html is not stored; the HTML is shown via the extended feedback modal (after deadline).

        // Skip result.json, it has already been processed
        $feedback_files = array_filter($files, fn($file) => $file->getClientFilename() !== 'result.json');
        ilExAutoScoreSubmissionFiles::replaceFeedbackFiles($assignment, $user_id, $feedback_files);
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
     * Is the host in a private/local network? Names are resolved for this.
     * If unresolvable, returns false so the request goes through the proxy.
     */
    protected function isInternalHost(string $host): bool
    {
        $ip = filter_var($host, FILTER_VALIDATE_IP) !== false ? $host : gethostbyname($host);

        // gethostbyname() returns the name unchanged on failure
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
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
            
            // The proxy only reaches outside; it rejects CONNECT to internal hosts (503), so call those directly.
            $host = trim((string) parse_url($url, PHP_URL_HOST), '[]'); // IPv6 comes bracketed
            $is_internal = $host !== '' && $this->isInternalHost($host);

            if ($is_internal) {
                curl_setopt($curl, CURLOPT_NOPROXY, $host);
            } elseif (($proxy = ilProxySettings::_getInstance())->isActive()) {
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

                // Specific hints for common cURL errors
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
            
            // Extract debug logs
            if (isset($decoded_result['debug_logs'])) {
                $this->debug_logs = $decoded_result['debug_logs'];
            }
            
            $success = isset($decoded_result['success']) ? (bool) $decoded_result['success'] : false;
            
            return $success;
            
        }
        catch(Throwable $e) {
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
        foreach (ilExAutoScoreSubmissionFiles::getFiles($submission) as $file) {
            $submitted[$file["filetitle"]] = $file;
        }
        foreach (ilExAutoScoreRequiredFile::getForAssignment($submission->getAssignment()->getId()) as $file) {
            if (isset($submitted[$file->getFilename()])) {
                $path = ilExAutoScoreSubmissionFiles::copyToTemp($submission->getAssignment(), $submitted[$file->getFilename()]);
                if ($path !== null) {
                    $this->temp_files[] = $path;
                    $postfiles[$file->getFilename()] = $path;
                }
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
        // './*' instead of '*': a file named like '--checkpoint-action=...' would otherwise become a tar option
        exec($tarcmd . ' ' . escapeshellarg($temptar) . ' ./*');
        chdir($curdir);

        $this->temp_dirs[] = $temproot . '/' . $tempdir;
        return $temproot . '/' . $tempdir . '/' . $temptar;
    }

    /**
     * Remove the temporary directories created by packFiles(); they hold copies of submissions
     */
    protected function removeTempDirs(): void
    {
        foreach ($this->temp_dirs as $dir) {
            try {
                ilFileUtils::delDir($dir);
            } catch (Throwable $e) {
                $this->logger()->warning('[ExAutoScore] could not remove temp dir', [
                    'dir'   => $dir,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        $this->temp_dirs = [];
    }

    /**
     * Send a failure notification
     * @param ilExAssignment    $assignment
     * @param ilExAutoScoreTask $scoreTask
     * @param string            $type
     */
    protected function notifyFailure($assignment, $scoreTask, $type)
    {
        // Global kill switch (plugin config): off = no mails at all.
        if (!$this->config->get('enable_failure_mails')) {
            return;
        }

        $scoreAss = ilExAutoScoreAssignment::findOrGetInstance($scoreTask->getAssignmentId());
        if (empty($scoreAss->getFailureMails())) {
            return;
        }

        // Throttle: only ONE failure mail per assignment until a run succeeds again
        // (receiveResult() resets the flag), i.e. one mail per outage, not per submission.
        if ($scoreAss->getFailureMailSent()) {
            return;
        }

        // Build subject/body per recipient language (see sendLocalizedNotification).
        $this->sendLocalizedNotification(
            $scoreAss->getFailureMails(),
            function (ilLanguage $lng, string $p) use ($assignment, $scoreTask, $type) {
                $subjectKey = ($type === self::NOTIFY_SEND_FAILURE)
                    ? 'failure_subject_send'
                    : 'failure_subject_result';
                $subject = sprintf($lng->txt($p . $subjectKey), $assignment->getTitle());

                $info = [];
                $info[$lng->txt('exc')] = ilObject::_lookupTitle($assignment->getExerciseId());
                $info[$lng->txt('exc_assignment')] = $assignment->getTitle();

                // Determine affected users (works for teams and single users)
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
                    $info[$lng->txt($p . 'submit_time')] = ilDatePresentation::formatDate(new ilDateTime($scoreTask->getSubmitTime(), IL_CAL_DATETIME));
                }
                if (!empty($scoreTask->getSubmitMessage())) {
                    $info[$lng->txt($p . 'submit_message')] = $scoreTask->getSubmitMessage();
                }
                if (!empty($scoreTask->getReturnTime())) {
                    $info[$lng->txt($p . 'return_time')] = ilDatePresentation::formatDate(new ilDateTime($scoreTask->getReturnTime(), IL_CAL_DATETIME));
                }
                if (!empty($scoreTask->getReturnCode())) {
                    $info[$lng->txt($p . 'return_code')] = $scoreTask->getReturnCode();
                }
                if (!empty($scoreTask->getTaskDuration())) {
                    $info[$lng->txt($p . 'task_duration')] = $scoreTask->getTaskDuration();
                }
                if (!empty($scoreTask->getInstantMessage())) {
                    $info[$lng->txt($p . 'instant_message')] = $scoreTask->getInstantMessage();
                }
                if (!empty($scoreTask->getInstantStatus())) {
                    $info[$lng->txt($p . 'instant_status')] = $scoreTask->getInstantStatus();
                }

                $body = '';
                foreach ($info as $label => $content) {
                    $body .= "$label: $content\n";
                }

                return [$subject, $body];
            }
        );

        // Arm the throttle until receiveResult() resets it on success.
        $scoreAss->setFailureMailSent(true);
        $scoreAss->store();
    }

    /**
     * Operator alert: the scoring service no longer accepts submissions (NOTIFY_SEND_FAILURE only).
     *
     * Separate from notifyFailure(): recipients come from the plugin config, not the exercise;
     * the throttle is global (an outage hits all exercises at once); and it has its own switch
     * (enable_admin_failure_mails), independent of the tutor mails' kill switch: muting tutor mails
     * must not blind operations, and the alert can be muted during maintenance without losing the recipients.
     *
     * @param ilExAssignment $assignment  assignment where the outage was noticed
     */
    protected function notifyServiceOutage($assignment): void
    {
        if (!$this->config->get('enable_admin_failure_mails')) {
            return;
        }

        $recipients = trim((string) $this->config->get('admin_failure_mails'));
        if ($recipients === '') {
            return;
        }

        // The alert is a side effect: failures are only logged, submission processing continues.
        // The settings access is inside the try because it is the first DB access.
        try {
            $settings = new ilSetting('exautoscore');
            if ((int) $settings->get('service_outage_mail_sent', '0')) {
                return;
            }

            $this->sendServiceOutageMail($assignment, $recipients, $settings);
        } catch (Throwable $e) {
            $this->logger()->warning('[ExAutoScore] could not send outage alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send the operator alert and arm the throttle; preconditions are checked in notifyServiceOutage().
     *
     * @param ilExAssignment $assignment
     * @param string         $recipients
     * @param ilSetting      $settings
     */
    protected function sendServiceOutageMail($assignment, string $recipients, $settings): void
    {
        // Build subject/body per recipient language (see sendLocalizedNotification).
        $this->sendLocalizedNotification(
            $recipients,
            function (ilLanguage $lng, string $p) use ($assignment) {
                $subject = $lng->txt($p . 'outage_subject');

                $body = $lng->txt($p . 'outage_intro') . "\n\n";
                $body .= $lng->txt('exc') . ': '
                    . ilObject::_lookupTitle($assignment->getExerciseId()) . "\n";
                $body .= $lng->txt('exc_assignment') . ': ' . $assignment->getTitle() . "\n";
                // Language-neutral format: the mail goes to operators, not learners.
                $body .= $lng->txt($p . 'outage_time') . ': ' . date('Y-m-d H:i') . "\n\n";
                $body .= $lng->txt($p . 'outage_hint');

                return [$subject, $body];
            }
        );

        $settings->set('service_outage_mail_sent', '1');
    }

    /**
     * Re-arm the operator alert once the service works again (successful send or run result).
     * Errors are caught and logged: resetting a mail throttle must never break a working submission.
     */
    protected function clearServiceOutageFlag(): void
    {
        try {
            $settings = new ilSetting('exautoscore');
            if ((int) $settings->get('service_outage_mail_sent', '0')) {
                $settings->set('service_outage_mail_sent', '0');
            }
        } catch (Throwable $e) {
            $this->logger()->warning('[ExAutoScore] could not reset outage alert flag', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify the recipients of the sample solution result, so the tutor need not check manually.
     * Only for the sample/example task, never for student submissions; $subjectKey selects success or failure.
     *
     * @param ilExAssignment    $assignment
     * @param ilExAutoScoreTask $scoreTask
     * @param string            $subjectKey
     */
    protected function notifySampleResult($assignment, $scoreTask, string $subjectKey): void
    {
        // Global kill switch (plugin config): off = no mails at all.
        if (!$this->config->get('enable_failure_mails')) {
            return;
        }

        $scoreAss = ilExAutoScoreAssignment::findOrGetInstance($scoreTask->getAssignmentId());
        if (empty($scoreAss->getFailureMails())) {
            return;
        }

        // Build subject/body per recipient language (see sendLocalizedNotification).
        $this->sendLocalizedNotification(
            $scoreAss->getFailureMails(),
            function (ilLanguage $lng, string $p) use ($assignment, $scoreTask, $subjectKey) {
                $subject = sprintf($lng->txt($p . $subjectKey), $assignment->getTitle());

                $info = [];
                $info[$lng->txt('exc')] = ilObject::_lookupTitle($assignment->getExerciseId());
                $info[$lng->txt('exc_assignment')] = $assignment->getTitle();
                if ($scoreTask->getReturnPoints() !== null) {
                    $info[$lng->txt($p . 'return_points')] = (string) $scoreTask->getReturnPoints();
                }
                if (!empty($scoreTask->getInstantStatus())) {
                    $info[$lng->txt($p . 'instant_status')] = $scoreTask->getInstantStatus();
                }
                if (!empty($scoreTask->getInstantMessage())) {
                    $info[$lng->txt($p . 'instant_message')] = $scoreTask->getInstantMessage();
                }
                if (!empty($scoreTask->getTaskDuration())) {
                    $info[$lng->txt($p . 'task_duration')] = $scoreTask->getTaskDuration();
                }

                $body = '';
                foreach ($info as $label => $content) {
                    $body .= "$label: $content\n";
                }

                return [$subject, $body];
            }
        );
    }

    /**
     * Send a plain-text notification, localized per recipient.
     *
     * Recipients (comma-separated logins or e-mail addresses) are grouped by
     * language: a known ILIAS login uses that user's language, a plain e-mail
     * address (not tied to a user) falls back to German. For each language the
     * $build callback produces [subject, body] from a matching ilLanguage and
     * the plugin key prefix. Runs in the results.php callback, which has no
     * user session — hence the explicit per-recipient language resolution.
     *
     * @param string   $recipients
     * @param callable $build  fn(ilLanguage $lng, string $prefix): array{0:string,1:string}
     */
    protected function sendLocalizedNotification(string $recipients, callable $build): void
    {
        $byLang = [];
        foreach (preg_split('/\s*,\s*/', trim($recipients), -1, PREG_SPLIT_NO_EMPTY) as $rcp) {
            $lang_key = $this->resolveRecipientLanguage($rcp);
            $byLang[$lang_key][] = $rcp;
        }

        $prefix = $this->plugin->getLangPrefix() . '_';
        foreach ($byLang as $lang_key => $rcps) {
            $lng = $this->plugin->getNotificationLanguage($lang_key);
            [$subject, $body] = $build($lng, $prefix);

            $mail = new ilMail(ANONYMOUS_USER_ID);
            $mail->enqueue(implode(',', $rcps), '', '', $subject, $body, []);
        }
    }

    /**
     * Language of a notification recipient. A login resolves to exactly one
     * account and its language is used. An e-mail address may belong to several
     * accounts (e.g. shared test logins), so its language is only used when all
     * matches agree; a genuine disagreement — or no match at all — falls back to
     * German. A resolved user without an own language preference yields the
     * installation default (see ilObjUser::_lookupLanguage).
     */
    protected function resolveRecipientLanguage(string $recipient): string
    {
        $usr_id = (int) ilObjUser::_lookupId($recipient);
        if ($usr_id) {
            return ilObjUser::_lookupLanguage($usr_id);
        }

        $languages = array_unique(array_map(
            fn($id) => ilObjUser::_lookupLanguage((int) $id),
            ilObjUser::getUserIdsByEmail($recipient)
        ));
        return count($languages) === 1 ? (string) reset($languages) : 'de';
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