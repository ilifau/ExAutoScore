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
            'length' => '250',

        ),
        'command' => array(
            'type' => 'text',
            'length' => '250',

        ),

    );
    if (! $ilDB->tableExists('exautoscore_assignment')) {
        $ilDB->createTable('exautoscore_assignment', $fields);
        $ilDB->addPrimaryKey('exautoscore_assignment', array( 'id' ));

        if (! $ilDB->sequenceExists('exautoscore_assignment')) {
            $ilDB->createSequence('exautoscore_assignment');
        }
    }
    $ilDB->addIndex('exautoscore_assignment', ['exercise_id'], 'i1');
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
            'length' => '250',

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
    }
?>