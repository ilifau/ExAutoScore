<#1>
<?php
    /**
     * Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
     * GPLv3, see docs/LICENSE
     */

    /**
     * ExAutoScore Plugin: database update script
     *
     * @author Fred Neumann <fred.neumann@fau.de>
     */

    global $DIC;
    $ilDB = $DIC->database();

?>
<#2>
<?php
    $fields = array(
        'id' => array(
            'notnull' => '1',
            'type' => 'integer',
            'length' => '4',

        ),
        'exercise_id' => array(
            'notnull' => '1',
            'type' => 'integer',
            'length' => '4',

        ),
        'uuid' => array(
            'type' => 'text',
            'length' => '50',

        ),
        'command' => array(
            'type' => 'text',
            'length' => '250',

        ),
        'min_points' => array(
            'type' => 'float',

        ),

    );
    if (! $ilDB->tableExists('exautoscore_assignment')) {
        $ilDB->createTable('exautoscore_assignment', $fields);
        $ilDB->addPrimaryKey('exautoscore_assignment', array( 'id' ));

        if (! $ilDB->sequenceExists('exautoscore_assignment')) {
            $ilDB->createSequence('exautoscore_assignment');
        }
        $ilDB->addIndex('exautoscore_assignment', ['exercise_id'], 'i1');
        $ilDB->addIndex('exautoscore_assignment', ['uuid'], 'i2');
    }

?>
<#3>
<?php
    $fields = array(
        'id' => array(
            'notnull' => '1',
            'type' => 'integer',
            'length' => '4',

        ),
        'assignment_id' => array(
            'notnull' => '1',
            'type' => 'integer',
            'length' => '4',

        ),
        'filename' => array(
            'type' => 'text',
            'length' => '250',

        ),
        'size' => array(
            'type' => 'integer',
            'length' => '4',

        ),
        'hash' => array(
            'type' => 'text',
            'length' => '50',

        ),
        'purpose' => array(
            'type' => 'text',
            'length' => '10',

        ),
        'description' => array(
            'type' => 'text',
            'length' => '2000',

        ),
        'is_public' => array(
            'type' => 'integer',
            'length' => '4',

        ),

    );
    if (! $ilDB->tableExists('exautoscore_prov_file')) {
        $ilDB->createTable('exautoscore_prov_file', $fields);
        $ilDB->addPrimaryKey('exautoscore_prov_file', array( 'id' ));

        if (! $ilDB->sequenceExists('exautoscore_prov_file')) {
            $ilDB->createSequence('exautoscore_prov_file');
        }
        $ilDB->addIndex('exautoscore_prov_file', ['assignment_id'], 'i1');
    }
?>
<#4>
<?php
    $fields = array(
        'id' => array(
            'notnull' => '1',
            'type' => 'integer',
            'length' => '4',

        ),
        'assignment_id' => array(
            'notnull' => '1',
            'type' => 'integer',
            'length' => '4',

        ),
        'filename' => array(
            'type' => 'text',
            'length' => '250',

        ),
        'size' => array(
            'type' => 'integer',
            'length' => '4',

        ),
        'hash' => array(
            'type' => 'text',
            'length' => '50',

        ),
        'description' => array(
            'type' => 'text',
            'length' => '2000',

        ),
        'required_encoding' => array(
            'type' => 'text',
            'length' => '250',

        ),
        'max_size' => array(
            'type' => 'integer',
            'length' => '4',
        ),

    );
    if (! $ilDB->tableExists('exautoscore_req_file')) {
        $ilDB->createTable('exautoscore_req_file', $fields);
        $ilDB->addPrimaryKey('exautoscore_req_file', array( 'id' ));

        if (! $ilDB->sequenceExists('exautoscore_req_file')) {
            $ilDB->createSequence('exautoscore_req_file');
        }
        $ilDB->addIndex('exautoscore_req_file', ['assignment_id'], 'i1');
    }
?>
<#5>
<?php
    $fields = array(
        'id' => array(
            'notnull' => '1',
            'type' => 'integer',
            'length' => '4',

        ),
        'assignment_id' => array(
            'notnull' => '1',
            'type' => 'integer',
            'length' => '4',

        ),
        'uuid' => array(
            'type' => 'text',
            'length' => '50',

        ),
        'user_id' => array(
            'type' => 'integer',
            'length' => '4',

        ),
        'team_id' => array(
            'type' => 'integer',
            'length' => '4',

        ),
        'submit_time' => array(
            'type' => 'timestamp',

        ),
        'submit_success' => array(
            'type' => 'integer',
            'length' => '4',

        ),
        'submit_message' => array(
            'type' => 'text',
            'length' => '250',

        ),
        'task_returncode' => array(
            'type' => 'integer',
            'length' => '4',

        ),
        'task_duration' => array(
            'type' => 'float',

        ),
        'return_time' => array(
            'type' => 'timestamp',

        ),
        'return_points' => array(
            'type' => 'float',

        ),
        'instant_message' => array(
            'type' => 'text',
            'length' => '4000',

        ),
        'instant_status' => array(
            'type' => 'text',
            'length' => '10',

        ),
        'protected_status' => array(
            'type' => 'text',
            'length' => '10',

        ),
        'protected_feedback_text' => array(
            'type' => 'text',
            'length' => '4000',

        ),
        'protected_feedback_html' => array(
            'type' => 'clob',

        ),

    );
    if (! $ilDB->tableExists('exautoscore_task')) {
        $ilDB->createTable('exautoscore_task', $fields);
        $ilDB->addPrimaryKey('exautoscore_task', array( 'id' ));

        if (! $ilDB->sequenceExists('exautoscore_task')) {
            $ilDB->createSequence('exautoscore_task');
        }

        $ilDB->addIndex('exautoscore_task', ['assignment_id'], 'i1');
        $ilDB->addIndex('exautoscore_task', ['uuid'], 'i2');
        $ilDB->addIndex('exautoscore_task', ['user_id'], 'i3');
        $ilDB->addIndex('exautoscore_task', ['team_id'], 'i4');
    }
?>
<#6>
<?php
    if (!$ilDB->tableExists('exautoscore_config'))
    {
        $fields = array(
            'param_name' => array(
                'type' => 'text',
                'length' => 255,
                'notnull' => true,
            ),
            'param_value' => array(
                'type' => 'text',
                'length' => 255,
                'notnull' => false,
                'default' => null
            )
        );
        $ilDB->createTable("exautoscore_config", $fields);
        $ilDB->addPrimaryKey("exautoscore_config", array("param_name"));
    }
?>
<#7>
<?php
if (!$ilDB->tableColumnExists('exautoscore_assignment', 'failure_mails'))
{
    $attributes = array(
            'type' => 'text',
            'length' => 255
    );
    $ilDB->addTableColumn("exautoscore_assignment", 'failure_mails', $attributes);
}
?>