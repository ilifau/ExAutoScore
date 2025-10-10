<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once './Modules/Exercise/classes/class.ilAssignmentHookPlugin.php';

/**
 * ExAutoScore Plugin for ILIAS 9
 * 
 * Provides automated scoring for programming assignments via external correction service.
 * Supports both individual and team-based programming assignments.
 * 
 * @author Fred Neumann <fred.neumann@fau.de>
 * @author Cornel Musielak <cornel.musielak@fau.de>
 */
class ilExAutoScorePlugin extends ilAssignmentHookPlugin
{
    // Assignment Type IDs
    const TYPE_AUTOSCORE_USER = 101;
    const TYPE_AUTOSCORE_TEAM = 102;

    /** @var ilExAutoScoreConfig */
    protected mixed $config;

    /** @var self */
    protected static $instance;

    /**
     * Constructor for ILIAS 9 compatibility
     */
    public function __construct(
        \ilDBInterface $db = null,
        \ilComponentRepositoryWrite $component_repository = null,
        string $id = ''
    ) {
        parent::__construct($db, $component_repository, $id);
    }

    /**
     * Get Plugin Name
     * Must match class name il<Name>Plugin and subdirectory name
     * 
     * @return string Plugin Name
     */
    public function getPluginName(): string 
    {
        return 'ExAutoScore';
    }

    /**
     * Get the singleton plugin instance
     * 
     * @return self
     */
    public static function getInstance(): self 
    {
        if (!isset(self::$instance)) {
            global $DIC;
            self::$instance = new self(
                $DIC->database(),
                $DIC['component.repository'],
                'exautoscore'
            );
        }
        return self::$instance;
    }

    /**
     * Get the plugin configuration
     * 
     * @return ilExAutoScoreConfig
     */
    public function getConfig(): mixed
    {
        if (!isset($this->config)) {
            require_once __DIR__ . '/param/class.ilExAutoScoreConfig.php';
            $this->config = new ilExAutoScoreConfig($this);
        }
        return $this->config;
    }

    /**
     * Uninstall custom data of this plugin
     */
    protected function uninstallCustom(): void
    {
        global $DIC;
        $db = $DIC->database();

        $db->dropTable('exautoscore_assignment', false);
        $db->dropTable('exautoscore_prov_file', false);
        $db->dropTable('exautoscore_req_file', false);
        $db->dropTable('exautoscore_task', false);
        $db->dropTable('exautoscore_config', false);
    }

    // =========================================================================
    // Assignment Type Registration
    // =========================================================================

    /**
     * Get the IDs of available assignment types
     * 
     * IMPORTANT: This method is called in two different contexts:
     * 1. When creating new assignments (dropdown in editor) 
     *    → Only users with creator rights should see the types
     * 2. When displaying existing assignments (for submission/viewing)
     *    → All users should see the types
     * 
     * We detect the context and filter accordingly.
     * 
     * @return int[] Array of assignment type IDs
     */
    public function getAssignmentTypeIds(): array 
    {
        global $DIC;
        
        // Try to detect if we're in the "create assignment" context
        $ctrl = $DIC->ctrl();
        $next_class = strtolower($ctrl->getNextClass());
        $current_class = strtolower($ctrl->getCmdClass());
        $cmd = $ctrl->getCmd();
        
        // Check if we're in the assignment editor (creating/editing)
        $is_editor_context = (
            $next_class === 'ilexassignmenteditorgui' || 
            $current_class === 'ilexassignmenteditorgui' ||
            in_array($cmd, ['addAssignment', 'createAssignment'])
        );
        
        if ($is_editor_context && !$this->canDefine()) {
            // User cannot create assignments of this type
            return [];
        }
        
        // In all other contexts: types are available
        return [self::TYPE_AUTOSCORE_USER, self::TYPE_AUTOSCORE_TEAM];
    }

    /**
     * Get an assignment type object by its ID
     * 
     * @param int $a_id Assignment type ID
     * @return ilExAssignmentTypeInterface|null
     */
    public function getAssignmentTypeById($a_id): ?ilExAssignmentTypeInterface 
    {
        switch ((int) $a_id) {
            case self::TYPE_AUTOSCORE_USER:
                require_once __DIR__ . '/class.ilExAssTypeAutoScoreUser.php';
                return new ilExAssTypeAutoScoreUser($this);

            case self::TYPE_AUTOSCORE_TEAM:
                require_once __DIR__ . '/class.ilExAssTypeAutoScoreTeam.php';
                return new ilExAssTypeAutoScoreTeam($this);
            
            default:
                return null;
        }
    }

    /**
     * Get an assignment type GUI by its ID
     * 
     * @param int $a_id Assignment type ID
     * @return ilExAssignmentTypeGUIInterface|null
     */
    public function getAssignmentTypeGUIById($a_id): ?ilExAssignmentTypeGUIInterface 
    {
        switch ((int) $a_id) {
            case self::TYPE_AUTOSCORE_USER:
                require_once __DIR__ . '/class.ilExAssTypeAutoScoreUserGUI.php';
                return new ilExAssTypeAutoScoreUserGUI($this);
                
            case self::TYPE_AUTOSCORE_TEAM:
                require_once __DIR__ . '/class.ilExAssTypeAutoScoreTeamGUI.php';
                return new ilExAssTypeAutoScoreTeamGUI($this);
                
            default:
                return null;
        }
    }

    /**
     * Get the class names of assignment type GUIs
     * 
     * @return array<int, string> Map of type ID to GUI class name
     */
    public function getAssignmentTypeGuiClassNames(): array 
    {
        return [
            self::TYPE_AUTOSCORE_USER => 'ilExAssTypeAutoScoreUserGUI',
            self::TYPE_AUTOSCORE_TEAM => 'ilExAssTypeAutoScoreTeamGUI'
        ];
    }

    // =========================================================================
    // Permissions & Access Control
    // =========================================================================

    /**
     * Check if the current user has administrative access
     * 
     * @return bool
     */
    public function hasAdminAccess(): bool
    {
        global $DIC;
        return $DIC->rbac()->system()->checkAccess('visible', SYSTEM_FOLDER_ID);
    }

    /**
     * Check if current user can CREATE/EDIT assignments of this plugin's types
     * 
     * This controls who can:
     * - Create new programming assignments
     * - Edit correction settings (Dockerfile, commands, etc.)
     * - Trigger container creation on correction server
     * 
     * NOTE: This does NOT control visibility of existing assignments!
     * Students can still SEE and SUBMIT to existing assignments.
     * 
     * @return bool
     */
    public function canDefine(): bool
    {
        global $DIC;

        // Admins can always create/edit
        if ($this->hasAdminAccess()) {
            return true;
        }

        // Check if creator roles are configured
        $roles_string = $this->getConfig()->get('creator_roles');
        
        // If no roles configured: only admins can create
        if (empty(trim($roles_string))) {
            return false;
        }

        // Check if current user has one of the creator roles
        $roles = explode(',', $roles_string);
        foreach ($roles as $role_id) {
            $role_id = (int) trim($role_id);
            
            if ($role_id <= 0) {
                continue;
            }
            
            if ($DIC->rbac()->review()->isAssigned($DIC->user()->getId(), $role_id)) {
                return true;
            }
        }
        
        // User doesn't have any matching role
        return false;
    }

    // =========================================================================
    // Service Integration
    // =========================================================================

    /**
     * Get the URL for the external correction service to send results back
     * 
     * @return string Full URL to results.php
     */
    public function getResultUrl(): string 
    {
        return ILIAS_HTTP_PATH . '/Customizing/global/plugins/Modules/Exercise/AssignmentHook/ExAutoScore/results.php';
    }

    // =========================================================================
    // Content Security
    // =========================================================================

    /**
     * Get allowed HTML tags for feedback display
     * 
     * Used to strip potentially dangerous HTML from correction service feedback.
     * - No elements outside body
     * - No frames, forms, audio, video, object
     * - No scripts
     * 
     * @see \ilUtil::stripScriptHTML()
     * @return string Allowed HTML tags
     */
    public function getAllowedTags(): string
    {
        return
            '<a><abbr><acronym><address><applet><area>' .
            '<big><blockquote><br>' .
            '<caption><center><cite><code><col><colgroup>' .
            '<dd><del><dfn><dir><div><dl><dt>' .
            '<em>' .
            '<font>' .
            '<h1><h2><h3><h4><h5><h6><hr>' .
            '<i><img><ins>' .
            '<kbd>' .
            '<li><link>' .
            '<map><menu>' .
            '<ol>' .
            '<p>' .
            '<q>' .
            '<s><samp><small><span><strike><strong><style><sub><sup>' .
            '<table><tbody><td><tfoot><th><thead><title><tr><tt>' .
            '<u><ul>' .
            '<var>';
    }

    // =========================================================================
    // File Storage
    // =========================================================================

    /**
     * Get the plugin directory name in ILIAS file storage
     * 
     * @return string Directory name
     */
    public static function getStorageDirectory(): string 
    {
        return 'exautoscore';
    }
}