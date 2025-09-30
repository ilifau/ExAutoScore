<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExAssTypeAutoScoreBaseGUI.php');

/**
 * @ilCtrl_IsCalledBy ilExAssTypeAutoScoreUserGUI: ilExSubmissionGUI, ilExAssignmentEditorGUI
 * @ilCtrl_Calls     ilExAssTypeAutoScoreUserGUI: ilExAutoScoreSettingsGUI, ilExAutoScoreProvidedFilesGUI, ilExAutoScoreRequiredFilesGUI
 */
class ilExAssTypeAutoScoreUserGUI extends ilExAssTypeAutoScoreBaseGUI implements ilExAssignmentTypeGUIInterface
{
}
