<?php

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


require_once (__DIR__ . '/class.ilExAutoScorePlugin.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreAssignment.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreProvidedFile.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreRequiredFile.php');

/**
 * Connector for the AuDoscore server
 */
class ilExAutoScoreConnector
{
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

        $url = $this->config->get('service_assignment_url');
        $timeout = (int) $this->config->get('service_timeout');

        $post = [];
        $post['api_key'] = $this->config->get('service_api_key');
        $post['name'] = $assignment->getTitle();
        $post['priority'] = 'False';
        $post['return_type'] = 'E';
        $post['return_address'] = 'fred.neumann@ili.fau.de';
        $post['command'] = $scoreAss->getCommand();
        $post['timeout'] = $timeout;

        $docker = ilExAutoScoreProvidedFile::getAssignmentDocker($assignment->getId());
        if (!empty($docker->getAbsolutePath())) {
            $post['dockerfile'] = new CURLFile($docker->getAbsolutePath(), '', $docker->getFilename());
        }

        $example = ilExAutoScoreRequiredFile::getForAssignment($assignment->getId());
        if (!empty($example)) {
            $file = array_pop($example);
            if (!empty($file->getAbsolutePath())) {
                $post['example'] = new CURLFile($file->getAbsolutePath(), '', $file->getFilename());
            }
        }

        $support = ilExAutoScoreProvidedFile::getAssignmentSupportFiles($assignment->getId());
        if (!empty($support)) {
            $file = array_pop($support);
            if (!empty($file->getAbsolutePath())) {
                $post['files'] = new CURLFile($file->getAbsolutePath(), '', $file->getFilename());
            }
        }

        return $post;
        //return $this->callService($url, $post, $timeout);
    }

    /**
     * @param ilExAssignment $assignment
     * @param ilObjUser $user
     * @return bool
     * @throws ilCurlConnectionException
     */
    public function sendTask($assignment, $user)
    {
        $scoreAss = ilExAutoScoreAssignment::findOrGetInstance($assignment->getId());

        $url = $this->config->get('service_task_url');
        $timeout = (int) $this->config->get('service_timeout');

        $post = [];
        $post['assignment'] = "dfb5e5d0-03d5-44a5-8b4a-eb8ffdb5b0f7";
        $post['user_identifier'] = $user->getLogin();

        $example = ilExAutoScoreRequiredFile::getForAssignment($assignment->getId());
        if (!empty($example)) {
            $file = array_pop($example);
            if (!empty($file->getAbsolutePath())) {
                $post['user_file'] = new CURLFile($file->getAbsolutePath(), '', $file->getFilename());
            }
        }

        return $post;
        //return $this->callService($url, $post, $timeout);
    }

    /**
     * Call the external service
     * @param string $url
     * @param array  $post
     * @param int    $timeout
     * @return string
     */
    public function callService($url, $post, $timeout)
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
}