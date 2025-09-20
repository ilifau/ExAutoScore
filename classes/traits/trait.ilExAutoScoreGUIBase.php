<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

trait ilExAutoScoreGUIBase
{
    /** @var ilObjUser */
    protected mixed $user;

    /** @var  ilAccessHandler $access */
    protected mixed $access;

    /** @var ilCtrl $ctrl */
    protected mixed $ctrl;

    /** @var  ilLanguage $lng */
    protected mixed $lng;

    /** @var ilTabsGUI */
    protected mixed $tabs;

    /** @var  ilToolbarGUI $toolbar */
    protected mixed $toolbar;

    /** @var ilGlobalTemplate $tpl */
    protected mixed $tpl;

    /** @var ilExAssTypeAutoScoreBaseGUI $parentGUI */
    protected mixed $parentGUI;

    protected function initGlobals()
    {
        global $DIC;
        $this->user = $DIC->user();
        $this->access = $DIC->access();
        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->tabs = $DIC->tabs();
        $this->toolbar = $DIC->toolbar();
        $this->tpl = $DIC->ui()->mainTemplate();
    }

    /**
     * Get the parent GUI object
     * @return ilExAssTypeAutoScoreBaseGUI
     */
    public function getParentGUI(): mixed {
        return $this->parentGUI;
    }
}