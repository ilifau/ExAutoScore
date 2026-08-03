<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * ExAutoScore plugin config class
 *
 * @author Fred Neumann <fred.neumann@ili.fau.de>
 *
 */
class ilExAutoScoreConfig
{
    /**
     * @var ilExAutoScorePlugin
     */
    protected $plugin;

    /**
     * @var ilExAutoScoreParam[] parameters
     */
    protected array $params = [];

	/**
	 * @var ilExAutoScoreParam[]	$params		parameters: 	name => ilExAutoScoreParam
	 */

	/**
	 * Constructor.
	 * @param ilPlugin|string $a_plugin_object
	 */
    public function __construct($a_plugin_object = "")
    {
        $this->plugin = $a_plugin_object;
        if (!class_exists('ilExAutoScoreParam')) {
            require_once(__DIR__ . '/class.ilExAutoScoreParam.php');
        }

        /** @var ilExAutoScoreParam[] $params */
        $params = array();

        $params[] = ilExAutoScoreParam::_create(
            'service_assignment_url',
            $this->plugin->txt('service_assignment_url'),
            $this->plugin->txt('service_assignment_url_info'),
            ilExAutoScoreParam::TYPE_TEXT,
            ''
        );

        $params[] = ilExAutoScoreParam::_create(
            'service_task_url',
            $this->plugin->txt('service_task_url'),
            $this->plugin->txt('service_task_url_info'),
            ilExAutoScoreParam::TYPE_TEXT,
            ''
        );

        $params[] = ilExAutoScoreParam::_create(
            'service_api_key',
            $this->plugin->txt('service_api_key'),
            $this->plugin->txt('service_api_key_info'),
            ilExAutoScoreParam::TYPE_TEXT,
            ''
        );

        $params[] = ilExAutoScoreParam::_create(
            'service_timeout',
            $this->plugin->txt('service_timeout'),
            $this->plugin->txt('service_timeout_info'),
            ilExAutoScoreParam::TYPE_INT,
            ''
        );

        $params[] = ilExAutoScoreParam::_create(
            'creator_roles',
            $this->plugin->txt('creator_roles'),
            $this->plugin->txt('creator_roles_info'),
            ilExAutoScoreParam::TYPE_ROLES,
            []
        );
        
        // NEU: Debug-Modus global aktivieren/deaktivieren
        $params[] = ilExAutoScoreParam::_create(
            'enable_debug_logs',
            $this->plugin->txt('enable_debug_logs'),
            $this->plugin->txt('enable_debug_logs_info'),
            ilExAutoScoreParam::TYPE_BOOLEAN,
            false
        );

        // Globaler Schalter für die E-Mail-Benachrichtigungen (Fehler- und
        // Musterlösungs-Erfolgsmails). Aus = das Empfänger-Feld je Übung wird
        // ausgeblendet UND es werden keine Mails verschickt.
        $params[] = ilExAutoScoreParam::_create(
            'enable_failure_mails',
            $this->plugin->txt('enable_failure_mails'),
            $this->plugin->txt('enable_failure_mails_info'),
            ilExAutoScoreParam::TYPE_BOOLEAN,
            false
        );

        // Eigener Schalter für den Betreiber-Alarm — bewusst getrennt von
        // enable_failure_mails. Wer die Dozenten-Mails abschaltet, will den
        // Betriebs-Alarm meist behalten; und wer ihn abschaltet (Wartung, Umzug,
        // bekannte Störung), will die Empfänger-Adressen nicht dabei verlieren.
        $params[] = ilExAutoScoreParam::_create(
            'enable_admin_failure_mails',
            $this->plugin->txt('enable_admin_failure_mails'),
            $this->plugin->txt('enable_admin_failure_mails_info'),
            ilExAutoScoreParam::TYPE_BOOLEAN,
            false
        );

        // Betreiber-Adresse(n) für Störungen des Korrektur-Service. Bewusst getrennt
        // vom Empfänger-Feld der Übung: dort trägt sich der Dozent ein und bekommt
        // auch fachliche Fehler seiner eigenen Aufgabe; hier steht, wer den Betrieb
        // macht, und bekommt ausschliesslich "der Service antwortet nicht".
        $params[] = ilExAutoScoreParam::_create(
            'admin_failure_mails',
            $this->plugin->txt('admin_failure_mails'),
            $this->plugin->txt('admin_failure_mails_info'),
            ilExAutoScoreParam::TYPE_TEXT,
            ''
        );

        $params[] = ilExAutoScoreParam::_create(
            'tar_command',
            $this->plugin->txt('tar_command'),
            $this->plugin->txt('tar_command_info'),
            ilExAutoScoreParam::TYPE_TEXT,
            '/bin/tar czf'
        );

        $params[] = ilExAutoScoreParam::_create(
            'untar_command',
            $this->plugin->txt('untar_command'),
            $this->plugin->txt('untar_command_info'),
            ilExAutoScoreParam::TYPE_TEXT,
            '/bin/tar xzf'
        );

        foreach ($params as $param)
        {
            $this->params[$param->name] = $param;
        }
        $this->read();
    }

    /**
     * Get the array of all parameters
     * @return ilExAutoScoreParam[]
     */
	public function getParams(): mixed
    {
        return $this->params;
    }

    /**
     * Get the value of a named parameter
     * @param $name
     * @return  mixed
     */
	public function get(string $name): mixed
    {
        if (!isset($this->params[$name]))
        {
            return null;
        }
        else
        {
            return $this->params[$name]->value;
        }
    }

    /**
     * Set the value of the named parameter
     * @param string $name
     * @param mixed $value
     *
     */
    public function set($name, $value = null)
    {
        $param = $this->params[$name];

        if (isset($param))
        {
            $param->setValue($value);
        }
    }


    /**
     * Read the configuration from the database
     */
	public function read(): void
    {
        global $DIC;
        $ilDB = $DIC->database();

        $query = "SELECT * FROM exautoscore_config";
        $res = $ilDB->query($query);
        while($row = $ilDB->fetchAssoc($res))
        {
            $this->set($row['param_name'], $row['param_value']);
        }
    }

    /**
     * Write the configuration to the database
     */
    public function write(): void
    {
        global $DIC;
        $ilDB = $DIC->database();

        foreach ($this->params as $param)
        {
            $ilDB->replace('exautoscore_config',
                array('param_name' => array('text', $param->name)),
                array('param_value' => array('text', (string) $param->value))
            );
        }
    }
}