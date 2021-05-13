<?php

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
    protected $plugin;

    /** @var ilExAutoScoreConfig */
    protected $config;

    /** @var string */
    protected $result_uuid;

    /** @var string */
    protected $result_message;



    public function __construct()
    {
        $this->plugin = ilExAutoScorePlugin::getInstance();
        $this->config = $this->plugin->getConfig();
    }

    /**
     * @param ilExAssignment $assignment
     */
    public function sendAssignment($assignment)
    {
        $scoreAss = ilExAutoScoreAssignment::findOrGetInstance($assignment->getId());
        $scoreTask = ilExAutoScoreTask::getExampleTask($assignment->getId());

        $url = $this->config->get('service_assignment_url');
        $timeout = (int) $this->config->get('service_timeout');

        $post = [];
        $post['api_key'] = $this->config->get('service_api_key');
        $post['name'] = $assignment->getTitle();
        $post['priority'] = 'False';
        $post['return_type'] = 'U';
        $post['return_address'] = $this->plugin->getResultUrl();
        $post['command'] = $scoreAss->getCommand();
        $post['timeout'] = $timeout;

        $docker = ilExAutoScoreProvidedFile::getAssignmentDocker($assignment->getId());
        if (!empty($docker->getAbsolutePath())) {
            $post['dockerfile'] = new CURLFile($docker->getAbsolutePath(), '', $docker->getFilename());
        }

        $provided = ilExAutoScoreProvidedFile::getAssignmentSupportFiles($assignment->getId());
        $this->addAssignmentFiles($post, $provided, 'files', 'provided.tgz');

        $required = ilExAutoScoreRequiredFile::getForAssignment($assignment->getId());
        $this->addAssignmentFiles($post, $required, 'example', 'required.tgz');

        $submitTime = new ilDateTime(time(), IL_CAL_UNIX);
        $scoreTask->setSubmitTime($submitTime->get(IL_CAL_DATETIME));

        $success =  $this->callService($url, $post, $timeout);

        $scoreTask->clearSubmissionData();
        $scoreTask->setSubmitTime($submitTime);
        $scoreTask->setUuid(($this->getResultUuid()));
        $scoreTask->setSubmitSuccess($success);
        $scoreTask->setSubmitMessage($this->getResultMessage());
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
     * @throws ilCurlConnectionException
     */
    public function sendExampleTask($assignment, $user)
    {
        $scoreAss = ilExAutoScoreAssignment::findOrGetInstance($assignment->getId());
        $scoreTask = ilExAutoScoreTask::getExampleTask($assignment->getId());

        $url = $this->config->get('service_task_url');
        $timeout = (int) $this->config->get('service_timeout');

        $post = [];
        $post['assignment'] = $scoreAss->getUuid();
        $post['user_identifier'] = $user->getLogin();

        $required = ilExAutoScoreRequiredFile::getForAssignment($assignment->getId());
        $this->addAssignmentFiles($post, $required, 'user_file', 'required.tgz');

        $submitTime = new ilDateTime(time(), IL_CAL_UNIX);
        $scoreTask->setSubmitTime($submitTime->get(IL_CAL_DATETIME));

        $success =  $this->callService($url, $post, $timeout);

        $scoreTask->clearSubmissionData();
        $scoreTask->setUuid(($this->getResultUuid()));
        $scoreTask->setSubmitSuccess($success);
        $scoreTask->setSubmitMessage($this->getResultMessage());
        $scoreTask->save();

        return $success;
    }

    /**
     * @param ilExSubmission $submission
     * @param ilObjUser $user
     * @return bool
     * @throws ilCurlConnectionException
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

        $this->addSubmissionFiles($post, $submission, 'user_file', 'required.tgz');

        $submitTime = new ilDateTime(time(), IL_CAL_UNIX);

        $success =  $this->callService($url, $post, $timeout);

        $scoreTask->clearSubmissionData();
        $scoreTask->setSubmitTime($submitTime->get(IL_CAL_DATETIME));
        $scoreTask->setUuid(($this->getResultUuid()));
        $scoreTask->setSubmitSuccess($success);
        $scoreTask->setSubmitMessage($this->getResultMessage());
        $scoreTask->save();
        $scoreTask->updateMemberStatus();

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

        $content = $DIC->http()->request()->getBody()->getContents();

        $result = json_decode($content, true);

        if (isset($result['assignment_uuid'])) {
            $this->result_uuid = (string) $result['assignment_uuid'];
        }
        if (isset($result['task_uuid'])) {
            $this->result_uuid = (string) $result['task_uuid'];
        }

        $task =  ilExAutoScoreTask::getByUuid($this->result_uuid);
        if (isset($task)) {
            $returnTime = new ilDateTime(time(), IL_CAL_UNIX);
            $task->setReturnTime($returnTime->get(IL_CAL_DATETIME));
            $task->setReturncode((int) $result['task_returncode']);
            $task->setReturnPoints((float) $result['points']);
            $task->setTaskDuration((float) $result['task_time']);
            $task->setInstantStatus($result['instant_status']);
            $task->setInstantMessage($result['instant_message']);
            $task->setProtectedStatus($result['protected_status']);
            $task->setProtectedFeedbackText($result['protected_feedback_text']);
            $task->setProtectedFeedbackHtml($result['protected_feedback_html']);
            $task->save();
            $task->updateMemberStatus();
        }

        if (!$result['success']) {
            $assignment = new ilExAssignment($task->getAssignmentId());
            $this->notifyFailure($assignment, $task, self::NOTIFY_RESULT_FAILURE);
        }
    }

    /**
     * Get the assignment uuid that is returned
     * @return string
     */
    public function getResultUuid() {
        return $this->result_uuid;
    }

    /**
     * @return string
     */
    public function getResultMessage() {
        return $this->result_message;
    }


    /**
     * Call the external service
     * @param string $url
     * @param array  $post
     * @param int    $timeout
     * @return string
     */
    protected function callService($url, $post, $timeout)
    {
        try {
            $curlConnection = new ilCurlConnection($url);
            $curlConnection->init();
            $proxy = ilProxySettings::_getInstance();
            if ($proxy->isActive()) {
                $curlConnection->setOpt(CURLOPT_HTTPPROXYTUNNEL, true);
                if (!empty($proxy->getHost())) {
                    $curlConnection->setOpt(CURLOPT_PROXY, $proxy->getHost());
                }
                if (!empty($proxy->getPort())) {
                    $curlConnection->setOpt(CURLOPT_PROXYPORT, $proxy->getPort());
                }
            }
            $curlConnection->setOpt(CURLOPT_RETURNTRANSFER, true);
            $curlConnection->setOpt(CURLOPT_VERBOSE, false);
            $curlConnection->setOpt(CURLOPT_TIMEOUT, $timeout);
            $curlConnection->setOpt(CURLOPT_POST, 1);
            $curlConnection->setOpt(CURLOPT_POSTFIELDS, $post);

            $result = $curlConnection->exec();
            $result = json_decode($result, true);
            if (isset($result['assignment_uuid'])) {
                $this->result_uuid = (string) $result['assignment_uuid'];
            }
            if (isset($result['task_uuid'])) {
                $this->result_uuid = (string) $result['task_uuid'];
            }
            $this->result_message = (string) $result['message'];
            return (bool) $result['success'];
        }
        catch(Exception $e) {
            if (isset($curlConnection)) {
                $curlConnection->close();
            }
            $this->result_uuid = null;
            $this->result_message = $e->getMessage();
            return false;
        }
    }

    /**
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
     * @param array $post
     * @param ilExSubmission $submission
     * @param string $postvar
     * @param string $tarname
     */
    protected function addSubmissionFiles(&$post, $submission, $postvar, $tarname)
    {
        $existing = [];
        foreach ($submission->getFiles() as $file) {
            // $file['filetitle'] is basename
            // $file['filename'] is absolute path
            $existing[$file["filetitle"]] = $file['filename'];
        }
        $required = ilExAutoScoreRequiredFile::getForAssignment($submission->getAssignment()->getId());
        if (count($required) == 1) {
            $file = array_pop($required);
            if (isset($existing[$file->getFilename()])) {
                $post[$postvar] = new CURLFile($existing[$file->getFilename()], '', $file->getFilename());
            }
        }
        elseif (count($required) > 1) {
            $postfiles = [];
            foreach ($required as $file) {
                if (isset($existing[$file->getFilename()])) {
                   $postfiles[$file->getFilename()] = $existing[$file->getFilename()];
                }
            }
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

        if (!empty($scoreTask->getUserId())) {
            $info[$lng->txt('user')] = ilObjUser::_lookupFullname($scoreTask->getUserId());
        }
        if (!empty($scoreTask->getTeamId())) {
            $team = new ilExAssignmentTeam($scoreTask->getTeamId());
            $names = [];
            foreach ($team->getMembers() as $user_id) {
                $names[] = ilObjUser::_lookupFullname($user_id);
            }
            $info[$lng->txt('exc_team')] = '(' . $team->getId() . ') ' . implode(', ', $names) . "\n";
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

        try {
            $mail = new ilMail(ANONYMOUS_USER_ID);
            $mail->sendMail($scoreAss->getFailureMails(), '', '', $subject, $body, [], ['system']);
        }
        catch (Exception $e) {
            return;
        }
    }
}