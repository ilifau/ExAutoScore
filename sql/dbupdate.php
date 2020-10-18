<#1>
<?php
    /**
     * Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
     * GPLv3, see docs/LICENSE
     */

    /**
     * Essay Test Plugin: database update script
     *
     * @author Fred Neumann <fred.neumann@fau.de>
     */

    $fields = array(
    'id' => array(
        'notnull' => '1',
        'type' => 'integer',
        'length' => '4',

    ),
    'container_id' => array(
        'notnull' => '1',
        'type' => 'integer',
        'length' => '4',

    ),

    );
    if (! $ilDB->tableExists('exautoscore_assignment')) {
        $ilDB->createTable('exautoscore_assignment', $fields);
        $ilDB->addPrimaryKey('exautoscore_assignment', array( 'id' ));

        if (! $ilDB->sequenceExists('exautoscore_assignment')) {
            $ilDB->createSequence('exautoscore_assignment');
        }
    }
?>
<#2>
    <?php
    $fields = array(
        'id' => array(
            'notnull' => '1',
            'type' => 'integer',
            'length' => '4',

        ),
        'title' => array(
            'type' => 'text',
            'length' => '250',

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
        'orig_assignment_id' => array(
            'type' => 'integer',
            'length' => '4',

        ),
        'is_public' => array(
            'type' => 'integer',
            'length' => '4',

        ),

    );
    if (! $ilDB->tableExists('exautoscore_container')) {
        $ilDB->createTable('exautoscore_container', $fields);
        $ilDB->addPrimaryKey('exautoscore_container', array( 'id' ));

        if (! $ilDB->sequenceExists('exautoscore_container')) {
            $ilDB->createSequence('exautoscore_container');
        }

    }
?>
