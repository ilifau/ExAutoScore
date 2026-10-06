<?php
declare(strict_types=1);

// Copyright (c) 2026 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Whitelist sanitizer for the extended feedback HTML sent by the correction service
 *
 * Replaces ilUtil::stripScriptHTML(), which removed only a fixed list of tags and let
 * event handlers on unknown tags through (e.g. <svg onload=...>).
 * Images are not allowed: external ones would leak the viewer's IP to a third party.
 */
class ilExAutoScoreFeedbackPurifier extends ilHtmlPurifierAbstractLibWrapper
{
    public const TAGSET = [
        'a', 'abbr', 'acronym', 'address', 'b', 'big', 'blockquote', 'br',
        'caption', 'center', 'cite', 'code', 'col', 'colgroup',
        'dd', 'del', 'dfn', 'dir', 'div', 'dl', 'dt', 'em', 'font',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'ins', 'kbd',
        'li', 'mark', 'menu', 'ol', 'p', 'pre', 'q', 's', 'samp', 'small', 'span',
        'strike', 'strong', 'sub', 'sup',
        'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'tt', 'u', 'ul', 'var',
    ];

    protected function getPurifierConfigInstance(): HTMLPurifier_Config
    {
        return self::buildConfig(ilHtmlPurifierAbstractLibWrapper::_getCacheDirectory());
    }

    /**
     * @param string|null $cache_dir null disables the definition cache (used by the unit tests)
     */
    public static function buildConfig(?string $cache_dir): HTMLPurifier_Config
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.DefinitionID', 'exautoscore feedback');
        $config->set('HTML.DefinitionRev', 1);
        if ($cache_dir === null) {
            $config->set('Cache.DefinitionImpl', null);
        } else {
            $config->set('Cache.SerializerPath', $cache_dir);
        }
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.AllowedElements', self::TAGSET);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        // <mark> is HTML5 and unknown to HTMLPurifier's HTML 4 definition
        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addElement('mark', 'Inline', 'Inline', 'Common');
        }
        return $config;
    }
}
